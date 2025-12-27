function addCustomButton() {
    const footerActions = document.querySelector('.wc-block-mini-cart__footer-actions');
    if (!footerActions) return;

    // zapobiegamy duplikowaniu
    if (document.querySelector('.custom-continue-button')) return;

    const btn = document.createElement('button');
    btn.className = 'wc-block-components-button wp-element-button custom-continue-button outlined';
    btn.innerHTML = '<div class="wc-block-components-button__text">Kontynuuj zakupy</div>';

    // Kiedy klikniesz → udawaj kliknięcie w X
    btn.addEventListener('click', () => {
        const closeBtn = document.querySelector('.wc-block-components-drawer__close');
        if (closeBtn) closeBtn.click();
    });

    footerActions.insertBefore(btn, footerActions.firstChild);
}

document.addEventListener('DOMContentLoaded', addCustomButton);

document.body.addEventListener('wc-blocks_added_to_cart', () => {
    setTimeout(addCustomButton, 100);
});

document.body.addEventListener('wc-blocks-mini-cart-toggle', () => {
    setTimeout(addCustomButton, 100);
});
