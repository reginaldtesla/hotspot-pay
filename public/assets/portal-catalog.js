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
            '<button type="button" class="pkg-row' + featured + '" data-package="' + escapeHtml(pkg.name) + '" data-slug="' + escapeHtml(pkg.slug) + '" data-buy-url="' + escapeHtml(pkg.buy_url) + '" aria-pressed="false">' +
                '<span class="pkg-row-info">' +
                    '<span class="pkg-row-name">' + escapeHtml(pkg.name) + '</span>' +
                    '<span class="pkg-row-meta">' + escapeHtml(pkg.data_label) + '</span>' +
                '</span>' +
                '<span class="pkg-row-price">' + escapeHtml(formatGhs(pkg.price_ghs)) + '</span>' +
            '</button>'
        );
    }

    function syncPkgSlugMap(packages) {
        var map = buildPkgSlugMap(packages);
        if (!window.PKG_SLUG || typeof window.PKG_SLUG !== 'object') {
            window.PKG_SLUG = map;
            return;
        }
        Object.keys(window.PKG_SLUG).forEach(function (key) {
            delete window.PKG_SLUG[key];
        });
        Object.assign(window.PKG_SLUG, map);
    }

    function startCheckout(row) {
        var slug = row.getAttribute('data-slug');
        var name = row.getAttribute('data-package');
        var buyUrl = row.getAttribute('data-buy-url');
        if (!slug || !buyUrl) {
            return;
        }

        var panel = document.getElementById('packagesPanel');
        var statusEl = document.getElementById('paymentStatus');

        document.querySelectorAll('.pkg-row').forEach(function (r) {
            r.classList.remove('selected', 'loading');
            r.setAttribute('aria-pressed', 'false');
        });
        row.classList.add('selected', 'loading');
        row.setAttribute('aria-pressed', 'true');
        if (panel) {
            panel.classList.add('is-paying');
        }
        if (statusEl) {
            statusEl.hidden = false;
            statusEl.classList.add('visible');
            statusEl.innerHTML = '<span class="spinner-inline"></span>Opening Paystack for <strong>' +
                escapeHtml(name) + '</strong>… Please wait.';
        }

        window.location.href = buyUrl;
    }

    function bindPackageRows() {
        document.querySelectorAll('.pkg-row').forEach(function (row) {
            row.addEventListener('click', function () {
                startCheckout(row);
            });
        });
    }

    function mountPortal(options) {
        var payBase = options.payBase || 'https://pay.tesnet.xyz';
        var panel = document.getElementById('packagesPanel');
        var tabsWrap = document.querySelector('.pricing-tabs-wrap');
        var statusEl = document.getElementById('paymentStatus');

        if (!panel) {
            return;
        }

        panel.innerHTML = '<p class="login-hint buy-empty-msg">Loading packages\u2026</p>';

        fetchCatalog(payBase)
            .then(function (data) {
                syncPkgSlugMap(data.packages || []);
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

                var tapHint = document.getElementById('buyTapHint');
                if (tapHint) {
                    tapHint.hidden = false;
                }

                bindPackageRows();

                if (statusEl) {
                    statusEl.hidden = true;
                    statusEl.classList.remove('visible');
                    statusEl.textContent = '';
                }
            })
            .catch(function () {
                panel.innerHTML =
                    '<p class="login-hint buy-empty-msg">Could not load packages. Join TesNet Wi\u2011Fi and try again, or call 020&nbsp;050&nbsp;4248.</p>';
            });
    }

    window.TesnetPortalCatalog = {
        mount: mountPortal,
        fetch: fetchCatalog,
        formatGhs: formatGhs,
        startCheckout: startCheckout
    };
})();
