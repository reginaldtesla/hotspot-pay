(function () {
    'use strict';

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

    function fetchCatalog(payBase) {
        var url = payBase.replace(/\/$/, '') + '/packages.php';
        return fetch(url, { credentials: 'omit', cache: 'no-store' }).then(function (res) {
            if (!res.ok) {
                throw new Error('Catalog unavailable');
            }
            return res.json();
        });
    }

    function buildPkgSlugMap(packages) {
        var map = {};
        packages.forEach(function (pkg) {
            if (pkg.in_stock) {
                map[pkg.name] = pkg.slug;
            }
        });
        return map;
    }

    function renderPkgRow(pkg) {
        var featured = pkg.slug === 'student-choice' ? ' pkg-row--featured' : '';
        return (
            '<button type="button" class="pkg-row' + featured + '" data-package="' + escapeHtml(pkg.name) + '" aria-pressed="false">' +
                '<span class="pkg-row-info">' +
                    '<span class="pkg-row-name">' + escapeHtml(pkg.name) + '</span>' +
                    '<span class="pkg-row-meta">' + escapeHtml(pkg.data_label) + '</span>' +
                '</span>' +
                '<span class="pkg-row-price">' + escapeHtml(formatGhs(pkg.price_ghs)) + '</span>' +
            '</button>'
        );
    }

    function mountPortal(options) {
        var payBase = options.payBase || 'https://pay.tesnet.xyz';
        var panel = document.getElementById('packagesPanel');
        var payBtn = document.getElementById('payBtn');
        var tabsWrap = document.querySelector('.pricing-tabs-wrap');
        var statusEl = document.getElementById('paymentStatus');

        if (!panel) {
            return;
        }

        panel.innerHTML = '<p class="login-hint buy-empty-msg">Loading packages\u2026</p>';

        fetchCatalog(payBase)
            .then(function (data) {
                window.PKG_SLUG = buildPkgSlugMap(data.packages || []);
                var packages = data.packages || [];
                var dataPkgs = packages.filter(function (p) { return p.kind !== 'time'; });
                var timePkgs = packages.filter(function (p) { return p.kind === 'time'; });
                var dataInStock = dataPkgs.filter(function (p) { return p.in_stock; });
                var timeInStock = timePkgs.filter(function (p) { return p.in_stock; });
                var timeWaiting = timePkgs.filter(function (p) { return !p.in_stock; });
                var showTabs = dataInStock.length > 0 && (timeInStock.length > 0 || timeWaiting.length > 0);

                if (!data.has_any_stock) {
                    panel.innerHTML =
                        '<p class="login-hint buy-empty-msg">New packages are being updated. Check back soon or contact support at 020&nbsp;050&nbsp;4248.</p>';
                    if (payBtn) {
                        payBtn.hidden = true;
                        payBtn.disabled = true;
                    }
                    if (tabsWrap) {
                        tabsWrap.hidden = true;
                    }
                    return;
                }

                if (tabsWrap) {
                    tabsWrap.hidden = !showTabs;
                }

                var html = '';
                if (showTabs) {
                    html += '<div class="pricing-panel is-active" id="panel-data">';
                    html += '<div class="pkg-list" data-kind="data">';
                    dataInStock.forEach(function (pkg) {
                        html += renderPkgRow(pkg);
                    });
                    html += '</div></div>';
                    html += '<div class="pricing-panel" id="panel-time" hidden>';
                    if (timeInStock.length > 0) {
                        html += '<div class="pkg-list" data-kind="time">';
                        timeInStock.forEach(function (pkg) {
                            html += renderPkgRow(pkg);
                        });
                        html += '</div>';
                    }
                    if (timeWaiting.length > 0) {
                        html += '<p class="login-hint pkg-soon-msg">Time passes (' +
                            timeWaiting.map(function (p) { return escapeHtml(p.name); }).join(', ') +
                            ') are being prepared. Check back soon.</p>';
                    }
                    html += '</div>';
                } else {
                    html += '<div class="pkg-list">';
                    packages.filter(function (p) { return p.in_stock; }).forEach(function (pkg) {
                        html += renderPkgRow(pkg);
                    });
                    html += '</div>';
                    if (timeWaiting.length > 0 && dataInStock.length > 0) {
                        html += '<p class="login-hint pkg-soon-msg">Time passes are being prepared. Data bundles above are available now.</p>';
                    }
                }

                panel.innerHTML = html;

                if (payBtn) {
                    payBtn.hidden = false;
                    payBtn.disabled = Object.keys(window.PKG_SLUG).length === 0;
                }

                if (typeof window.portalBindPackageRows === 'function') {
                    window.portalBindPackageRows();
                }

                if (statusEl) {
                    statusEl.hidden = true;
                    statusEl.classList.remove('visible');
                    statusEl.textContent = '';
                }
            })
            .catch(function () {
                panel.innerHTML =
                    '<p class="login-hint buy-empty-msg">Could not load packages. Join TesNet Wi\u2011Fi and try again, or call 020&nbsp;050&nbsp;4248.</p>';
                if (payBtn) {
                    payBtn.hidden = true;
                    payBtn.disabled = true;
                }
            });
    }

    window.TesnetPortalCatalog = {
        mount: mountPortal,
        fetch: fetchCatalog,
        formatGhs: formatGhs
    };
})();
