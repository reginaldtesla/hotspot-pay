(function () {
    'use strict';

    var PAY_BASE = 'https://pay.tesnet.xyz';

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function formatGhs(priceGhs) {
        var n = parseFloat(priceGhs);
        if (Number.isNaN(n)) {
            return priceGhs;
        }
        if (Math.abs(n - Math.round(n)) < 0.001) {
            return 'GH\u20b5' + Math.round(n);
        }
        return 'GH\u20b5' + n.toFixed(2);
    }

    function renderCard(pkg, options) {
        var inStock = !!pkg.in_stock;
        var buyUrl = pkg.buy_url || (PAY_BASE + '/buy.php?pkg=' + encodeURIComponent(pkg.slug));
        var cardClass = 'pricing-card reveal flex flex-col rounded-2xl border p-6 text-left transition-shadow';
        cardClass += inStock
            ? ' border-gray-200 bg-white shadow-card hover:shadow-card-hover dark:border-gray-700 dark:bg-surface-dark'
            : ' border-dashed border-gray-300 bg-gray-50 opacity-90 dark:border-gray-600 dark:bg-gray-900/40';

        var action = inStock
            ? '<a class="btn-primary mt-6 inline-flex w-full items-center justify-center rounded-xl px-4 py-3 text-sm font-bold text-white" href="' +
                escapeHtml(buyUrl) + '">Buy now</a>'
            : '<span class="mt-6 inline-flex w-full items-center justify-center rounded-xl border border-gray-300 px-4 py-3 text-sm font-semibold text-text-muted dark:border-gray-600">Coming soon</span>';

        return (
            '<article class="' + cardClass + '">' +
                '<div class="flex items-start justify-between gap-3">' +
                    '<h3 class="text-lg font-bold text-text-main">' + escapeHtml(pkg.name) + '</h3>' +
                    (inStock
                        ? '<span class="rounded-full bg-primary/10 px-2.5 py-0.5 text-xs font-bold text-primary">Available</span>'
                        : '<span class="rounded-full bg-gray-200 px-2.5 py-0.5 text-xs font-bold text-text-muted dark:bg-gray-700">Soon</span>') +
                '</div>' +
                '<p class="mt-2 text-3xl font-extrabold text-primary">' + escapeHtml(formatGhs(pkg.price_ghs)) + '</p>' +
                '<p class="mt-1 text-sm text-text-muted">' + escapeHtml(pkg.data_label) + '</p>' +
                action +
            '</article>'
        );
    }

    function mountLanding(options) {
        options = options || {};
        var payBase = options.payBase || PAY_BASE;
        var emptyEl = document.getElementById('pricingEmpty');
        var contentEl = document.getElementById('pricingContent');
        var subtitleEl = document.getElementById('pricingSubtitle');
        var dataGrid = document.getElementById('pricingDataGrid');
        var timeGrid = document.getElementById('pricingTimeGrid');
        var timeSoonEl = document.getElementById('pricingTimeSoon');
        var dataSection = document.getElementById('pricingDataSection');
        var timeSection = document.getElementById('pricingTimeSection');

        if (!contentEl || !emptyEl) {
            return;
        }

        fetch(payBase.replace(/\/$/, '') + '/packages.php', { credentials: 'omit', cache: 'no-store' })
            .then(function (res) {
                if (!res.ok) {
                    throw new Error('Catalog unavailable');
                }
                return res.json();
            })
            .then(function (data) {
                var packages = data.packages || [];
                var dataPkgs = packages.filter(function (p) { return p.kind !== 'time'; });
                var timePkgs = packages.filter(function (p) { return p.kind === 'time'; });
                var dataInStock = dataPkgs.some(function (p) { return p.in_stock; });
                var timeInStock = timePkgs.some(function (p) { return p.in_stock; });
                var timeWaiting = timePkgs.some(function (p) { return !p.in_stock; });

                if (!data.has_any_stock && dataPkgs.length === 0 && timePkgs.length === 0) {
                    emptyEl.hidden = false;
                    contentEl.hidden = true;
                    return;
                }

                emptyEl.hidden = true;
                contentEl.hidden = false;

                if (subtitleEl) {
                    if (dataInStock && timeWaiting && !timeInStock) {
                        subtitleEl.textContent = 'Data bundles are live. Time passes are being prepared.';
                    } else if (data.has_any_stock) {
                        subtitleEl.textContent = 'Choose a plan and pay securely with Mobile Money or card.';
                    } else {
                        subtitleEl.textContent = 'New packages are being updated.';
                    }
                }

                if (dataGrid && dataSection) {
                    if (dataPkgs.length > 0) {
                        dataSection.hidden = false;
                        dataGrid.innerHTML = dataPkgs.map(function (pkg) {
                            return renderCard(pkg, { payBase: payBase });
                        }).join('');
                    } else {
                        dataSection.hidden = true;
                    }
                }

                if (timeGrid && timeSection && timeSoonEl) {
                    if (timePkgs.length > 0) {
                        timeSection.hidden = false;
                        timeSoonEl.hidden = true;
                        timeGrid.hidden = false;
                        timeGrid.innerHTML = timePkgs.map(function (pkg) {
                            return renderCard(pkg, { payBase: payBase });
                        }).join('');
                    } else {
                        timeSection.hidden = true;
                    }
                }

                document.querySelectorAll('#pricingContent .reveal').forEach(function (el) {
                    el.classList.add('is-visible');
                });
            })
            .catch(function () {
                if (subtitleEl) {
                    subtitleEl.textContent = 'Could not load live prices. Refresh the page or contact support.';
                }
            });
    }

    window.TesnetPricingCatalog = {
        mount: mountLanding
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            mountLanding();
        });
    } else {
        mountLanding();
    }
})();
