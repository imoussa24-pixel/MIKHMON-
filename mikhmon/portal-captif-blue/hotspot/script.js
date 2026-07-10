(function () {
  var translations = {
    fr: {
      brand: 'TIKRAS IT',
      eyebrow: 'WiFi haut debit securise',
      headline: 'Connectez-vous en quelques secondes.',
      lead: 'Achetez un voucher, saisissez vos identifiants et profitez d une connexion rapide depuis tous vos appareils.',
      buy: 'Acheter un voucher',
      support: 'Support WhatsApp',
      access: 'Acces client',
      loginTitle: 'Entrer mon voucher',
      voucherLabel: 'Code voucher',
      passwordLabel: 'Mot de passe',
      connect: 'Se connecter',
      needVoucher: 'Besoin d un code ?',
      printTicket: 'Ticket imprimable',
      session: 'Session',
      network: 'Reseau',
      payment: 'Paiement',
      qrTitle: 'Connexion QR',
      qrText: 'Scannez le QR d un ticket valide ou demandez un code au support.',
      footer: 'Disponible 24h/24. Pour assistance, contactez le support.'
    },
    en: {
      brand: 'TIKRAS IT',
      eyebrow: 'Secure high speed WiFi',
      headline: 'Get online in seconds.',
      lead: 'Buy a voucher, enter your credentials and enjoy a fast connection on every device.',
      buy: 'Buy a voucher',
      support: 'WhatsApp support',
      access: 'Customer access',
      loginTitle: 'Enter my voucher',
      voucherLabel: 'Voucher code',
      passwordLabel: 'Password',
      connect: 'Connect',
      needVoucher: 'Need a code?',
      printTicket: 'Printable ticket',
      session: 'Session',
      network: 'Network',
      payment: 'Payment',
      qrTitle: 'QR login',
      qrText: 'Scan a valid ticket QR or ask support for a code.',
      footer: 'Available 24/7. Contact support for help.'
    }
  };

  function setLanguage(lang) {
    var dictionary = translations[lang] || translations.fr;
    document.documentElement.lang = lang;
    Object.keys(dictionary).forEach(function (key) {
      var nodes = document.querySelectorAll('[data-i18n="' + key + '"]');
      Array.prototype.forEach.call(nodes, function (node) {
        node.textContent = dictionary[key];
      });
    });
    try { localStorage.setItem('portalLang', lang); } catch (error) {}
  }

  function renderOffers(data) {
    var container = document.getElementById('offers');
    if (!container || !data || !data.length) return;
    container.innerHTML = data.map(function (offer, index) {
      return '<article class="offer-card' + (index === 1 ? ' featured' : '') + '">' +
        '<span class="offer-name">' + offer.name + '</span>' +
        '<strong>' + offer.duration + '</strong>' +
        '<span>' + offer.price + '</span>' +
      '</article>';
    }).join('');
  }

  function startCountdown() {
    var node = document.getElementById('countdown');
    if (!node) return;
    var remaining = parseInt(node.getAttribute('data-duration'), 10) || 3600;
    function pad2(value) {
      return value < 10 ? '0' + value : String(value);
    }
    function tick() {
      var minutes = Math.floor(remaining / 60);
      var seconds = remaining % 60;
      node.textContent = pad2(minutes) + ':' + pad2(seconds);
      if (remaining > 0) remaining -= 1;
    }
    tick();
    window.setInterval(tick, 1000);
  }

  Array.prototype.forEach.call(document.querySelectorAll('[data-lang]'), function (button) {
    button.addEventListener('click', function () {
      setLanguage(button.getAttribute('data-lang'));
    });
  });

  setLanguage((function () {
    try { return localStorage.getItem('portalLang') || 'fr'; } catch (error) { return 'fr'; }
  })());
  startCountdown();

  if (window.fetch) {
    fetch('api/offers.php', { cache: 'no-store' })
      .then(function (response) { return response.ok ? response.json() : null; })
      .then(renderOffers)
      .catch(function () {});
  }
}());
