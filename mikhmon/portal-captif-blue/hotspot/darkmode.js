(function () {
  var key = 'portalTheme';
  var button = document.getElementById('darkModeBtn');

  function apply(mode) {
    document.body.classList.toggle('portal-dark', mode === 'dark');
    if (button) button.textContent = mode === 'dark' ? 'J' : 'N';
  }

  var saved = 'light';
  try { saved = localStorage.getItem(key) || 'light'; } catch (error) {}
  apply(saved);

  if (button) {
    button.addEventListener('click', function () {
      var next = document.body.classList.contains('portal-dark') ? 'light' : 'dark';
      try { localStorage.setItem(key, next); } catch (error) {}
      apply(next);
    });
  }
}());
