<?php
/**
 * Functions.php - Kadence Child Theme
 * WERSJA FINALNA: Naprawa Logic (Jeden przycisk na raz) + Manualny AJAX
 */

function kadence_child_enqueue_styles() {
    // Załaduj style motywu głównego
    wp_enqueue_style(
        'kadence-theme',
        get_template_directory_uri() . '/style.css'
    );

    // Załaduj style motywu potomnego
    wp_enqueue_style(
        'kadence-child-style',
        get_stylesheet_directory_uri() . '/style.css',
        array('kadence-theme'),
        wp_get_theme()->get('Version')
    );
    
    // Załaduj skrypt custom-mini-cart (przycisk kontynuuj)
    wp_enqueue_script(
        'custom-mini-cart-btn',
        get_stylesheet_directory_uri() . '/custom-mini-cart.js',
        array('jquery'),
        '1.0',
        true
    );
}
add_action('wp_enqueue_scripts', 'kadence_child_enqueue_styles');

// --- CSS: POPRAWKI WIZUALNE (Bez blokowania klikania) ---
add_action('wp_enqueue_scripts', function() {
    $handle = 'kadence-child-style';
    $custom_css = "
        /* Usunięcie gradientu i ustawienie tła pomarańczowego */
        li.entry.content-bg.loop-entry.product.type-product.status-publish {
            background: none !important;
        }

        li div.product-details.content-bg.entry-content-wrap {
            background: none !important;
        }
        
        /* Styl spinnera ładowania w przycisku */
        .main-variant-add-btn.loading {
            position: relative;
            color: transparent !important;
            pointer-events: none; /* Blokada klikania podczas ładowania */
        }
        .main-variant-add-btn.loading::after {
            content: '';
            position: absolute;
            top: 50%; left: 50%;
            margin: -8px 0 0 -8px;
            width: 16px; height: 16px;
            border: 2px solid #333;
            border-top-color: transparent;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin { from {transform:rotate(0deg);} to {transform:rotate(360deg);} }
    ";
    wp_add_inline_style($handle, $custom_css);
});

/**
 * 1. Zmienia tekst przycisku "w koszyku"
 */
add_filter( 'render_block', 'kd_modify_wc_product_button_render', 10, 2 );
function kd_modify_wc_product_button_render( $block_content, $block ) {
    if ( empty( $block['blockName'] ) || 'woocommerce/product-button' !== $block['blockName'] ) {
        return $block_content;
    }

    $content = $block_content;

    // A) Zmiana tekstu w HTML
    $content = preg_replace_callback(
        '/(<span\b[^>]*>)(\s*?)(\d+)\s+w\s+koszyku(\s*?)(<\/span>)/iu',
        function( $m ) {
            $count = $m[3];
            $new = $m[1] . $m[2] . 'Dodaj [' . $count . ' w koszyku]' . $m[4] . $m[5];
            return $new;
        },
        $content
    );

    // B) Modyfikacja kontekstu JSON
    if ( preg_match_all( '/data-wp-context=(["\'])(.*?)\1/si', $content, $m_ctx_all, PREG_SET_ORDER ) ) {
        foreach ( $m_ctx_all as $m_ctx ) {
            $quote = $m_ctx[1];
            $raw = $m_ctx[2];
            $decoded = html_entity_decode( $raw, ENT_QUOTES | ENT_HTML5 );
            $json = json_decode( $decoded, true );
            
            if ( json_last_error() === JSON_ERROR_NONE && is_array( $json ) ) {
                if ( isset( $json['inTheCartText'] ) && is_string( $json['inTheCartText'] ) ) {
                    $val = $json['inTheCartText'];
                    if ( preg_match('/###\s*w\s+koszyku|\\d+\\s+w\\s+koszyku/ui', $val ) || $val === '### w koszyku' ) {
                        if ( strpos( $val, '###' ) !== false ) {
                            $json['inTheCartText'] = str_replace( '### w koszyku', 'Dodaj [### w koszyku]', $val );
                        } else {
                            $json['inTheCartText'] = 'Dodaj [' . $val . ']';
                        }
                    }
                }
                $new_json_raw = wp_json_encode( $json );
                $new_json_escaped = htmlspecialchars( $new_json_raw, ENT_QUOTES | ENT_HTML5 );
                $old_attr = 'data-wp-context=' . $quote . $raw . $quote;
                $new_attr = 'data-wp-context=' . $quote . $new_json_escaped . $quote;
                $content = str_replace( $old_attr, $new_attr, $content );
            }
        }
    }
    return $content;
}

/**
 * 2. Wyświetlanie przełącznika wariantów
 */
add_filter( 'render_block', 'kadence_child_variant_selector_in_blocks', 20, 2 );
function kadence_child_variant_selector_in_blocks( $block_content, $block ) {
    if ( 'woocommerce/product-button' !== $block['blockName'] ) {
        return $block_content;
    }

    global $product;
    if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
        if ( isset( $block['attrs']['productId'] ) ) {
            $product = wc_get_product( $block['attrs']['productId'] );
        } else {
            $product = wc_get_product( get_the_ID() );
        }
    }

    if ( ! $product || ! $product->is_type( 'variable' ) ) {
        return $block_content;
    }

    $variations = $product->get_available_variations();
    if ( empty( $variations ) ) {
        return $block_content;
    }

    ob_start();
    ?>
    <div class="custom-variant-container" data-product-id="<?php echo esc_attr($product->get_id()); ?>">
        <div class="variant-selectors">
            <?php foreach ( $variations as $variation ) : 
                $variation_id = $variation['variation_id'];
                $attributes = $variation['attributes'];
                $label = reset( $attributes ); 
                ?>
                <div class="variant-option" 
                     data-variation-id="<?php echo esc_attr( $variation_id ); ?>">
                    <?php echo esc_html( $label ); ?>
                </div>
            <?php endforeach; ?>
        </div>

        <a href="#" 
           data-quantity="1" 
           data-product_id="" 
           data-variation_id="" 
           class="wp-element-button button main-variant-add-btn"
           aria-label="Wybierz wariant"
           rel="nofollow">
            Dodaj do koszyka
        </a>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * 3. SKRYPT JS: MANUALNY AJAX + POPRAWNE OTWIERANIE (TYLKO JEDEN PRZYCISK)
 */
add_action('wp_footer', 'kadence_child_variant_logic_script', 999); 
function kadence_child_variant_logic_script() {
    ?>
    <script>
    jQuery(document).ready(function($) {
        
        // A. Wybór wariantu
        $(document).on('click', '.variant-option', function() {
            var $option = $(this);
            var $container = $option.closest('.custom-variant-container');
            var $btn = $container.find('.main-variant-add-btn');
            var varId = $option.data('variation-id');

            $container.find('.variant-option').removeClass('active');
            $option.addClass('active');

            $btn.attr('data-product-id', varId); 
            $btn.attr('data-variation_id', varId);
        });

        // B. MANUALNY AJAX (Szybki, bez przeładowania)
        $(document).on('click', '.main-variant-add-btn', function(e) {
            e.preventDefault(); 
            
            var $btn = $(this);
            var varId = $btn.attr('data-variation_id');

            if ( !varId ) {
                alert("Najpierw wybierz wariant!");
                return false;
            }

            if ($btn.hasClass('loading')) return;
            $btn.addClass('loading');

            // Wysyłamy żądanie do WooCommerce
            $.ajax({
                type: 'POST',
                url: '/?wc-ajax=add_to_cart',
                data: {
                    product_id: varId,
                    quantity: 1
                },
                success: function(response) {
                    $btn.removeClass('loading');
                    
                    if (response.error && response.product_url) {
                        window.location = response.product_url;
                        return;
                    }

                    // 1. Odświeżamy koszyk (liczniki na stronie)
                    $(document.body).trigger('added_to_cart', [response.fragments, response.cart_hash, $btn]);
                    $(document.body).trigger('wc_fragment_refresh');

                    // 2. OTWIERAMY MINI KOSZYK
                    setTimeout(function() {
                        // SZUKAMY TYLKO WIDOCZNEGO PRZYCISKU!
                        // Na stronie są dwa przyciski (desktop/mobile). Kliknięcie obu naraz psuje overlay.
                        // ":visible" wybiera tylko ten, który aktualnie widzi użytkownik.
                        var $miniCartBtn = $('.wc-block-mini-cart__button:visible').first();
                        
                        // Jeśli nie znaleziono widocznego (rzadkie), weź dowolny pierwszy
                        if (!$miniCartBtn.length) {
                            $miniCartBtn = $('.wc-block-mini-cart__button').first();
                        }

                        // Kliknij tylko jeśli koszyk jest zamknięty
                        if ($miniCartBtn.attr('aria-expanded') !== 'true') {
                            $miniCartBtn.trigger('click');
                        }
                    }, 300);
                },
                error: function() {
                    $btn.removeClass('loading');
                    console.log('Błąd AJAX dodawania do koszyka');
                }
            });
        });

    });
    </script>
    <?php
}
?>