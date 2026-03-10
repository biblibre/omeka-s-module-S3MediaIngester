(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        const itemMediaFieldset = document.getElementById('item-media');
        if (!itemMediaFieldset) {
            return;
        }

        itemMediaFieldset.insertAdjacentHTML(
            'beforeend',
            `<div id="s3mediaingester-file-selector" class="sidebar">
                <div class="confirm-main">
                    <a href="#" class="sidebar-close o-icon-close"><span class="screen-reader-text">${Omeka.jsTranslate('Close')}</span></a>
                    <div class="sidebar-content">
                    </div>
                </div>

                <div class="confirm-panel">
                    <a href="#" class="button s3mediaingester-button-confirm">${Omeka.jsTranslate('Confirm')}</a>
                </div>
            </div>`
        );
        const sidebar = $('#s3mediaingester-file-selector');
        sidebar.on('click', function (ev) {
            if (!ev.target.hasAttribute('href')) {
                return;
            }

            ev.preventDefault();
            if (ev.target.classList.contains('s3mediaingester-button-confirm')) {
                const inputId = sidebar.data('input-id');
                const input = document.getElementById(inputId);
                const selected = sidebar.find('input[name="selected"]:checked').val();
                if (input && selected) {
                    input.value = selected;
                    Omeka.closeSidebar(sidebar);
                }

                return;
            }
            Omeka.populateSidebarContent(sidebar, ev.target.getAttribute('href'));
        });

        document.body.addEventListener('click', function (ev) {
            if (!ev.target.classList.contains('s3mediaingester-button-browse')) {
                return;
            }
            const inputId = ev.target.dataset.inputId;
            Omeka.populateSidebarContent(sidebar, event.target.dataset.sidebarUrl);
            Omeka.openSidebar(sidebar);
            sidebar.data('input-id', inputId);
        });
    });
})();
