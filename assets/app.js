// Comportamiento comun del panel y de la web publica (sin dependencias)
(function () {
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };

  // Menu lateral en movil
  var menu = $('#menu'), shade = $('#shade');
  if (menu) menu.addEventListener('click', function () { document.body.classList.toggle('nav'); });
  if (shade) shade.addEventListener('click', function () { document.body.classList.remove('nav'); });

  // Confirmaciones, filas clicables, filtros automaticos y copiar al portapapeles
  document.addEventListener('click', function (e) {
    var c = e.target.closest('[data-confirm]');
    if (c && !window.confirm(c.getAttribute('data-confirm'))) { e.preventDefault(); e.stopPropagation(); return; }
    var cp = e.target.closest('[data-copy]');
    if (cp) {
      var txt = cp.getAttribute('data-copy'), old = cp.textContent;
      var done = function () { cp.textContent = 'Copiado'; setTimeout(function () { cp.textContent = old; }, 1500); };
      if (navigator.clipboard && window.isSecureContext) navigator.clipboard.writeText(txt).then(done);
      else { var t = document.createElement('textarea'); t.value = txt; document.body.appendChild(t); t.select(); try { document.execCommand('copy'); done(); } catch (x) {} t.remove(); }
      return;
    }
    var tr = e.target.closest('tr[data-href]');
    if (tr && !e.target.closest('a,button,input,select,form,label')) window.location = tr.getAttribute('data-href');
  });
  $$('select[data-auto]').forEach(function (s) { s.addEventListener('change', function () { s.form.submit(); }); });

  // Reserva: selector de cantidades y total
  var form = $('#bookform');
  if (form) {
    var fmt = function (n) { return n.toLocaleString('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €'; };
    var total = $('#total'), btn = $('#buybtn');
    var update = function () {
      var sum = 0, n = 0;
      $$('.step', form).forEach(function (s) { var q = parseInt($('input', s).value, 10) || 0; sum += q * parseFloat(s.dataset.price); n += q; });
      total.textContent = fmt(sum);
      btn.disabled = n === 0;
      btn.textContent = n === 0 ? 'Elige tus entradas' : (sum > 0 ? 'Reservar ' + n + (n === 1 ? ' plaza' : ' plazas') + ' · ' + fmt(sum) : 'Reservar ' + n + (n === 1 ? ' plaza' : ' plazas'));
    };
    $$('.step', form).forEach(function (s) {
      var inp = $('input', s), max = parseInt(s.dataset.max, 10) || 10;
      $('.minus', s).addEventListener('click', function () { inp.value = Math.max(0, (parseInt(inp.value, 10) || 0) - 1); update(); });
      $('.plus', s).addEventListener('click', function () { inp.value = Math.min(max, (parseInt(inp.value, 10) || 0) + 1); update(); });
    });
    update();
    form.addEventListener('submit', function () { btn.disabled = true; btn.textContent = 'Un momento…'; });
    var bar = $('.stickybar'), box = $('#reservar');
    if (bar && box && 'IntersectionObserver' in window) new IntersectionObserver(function (en) { bar.classList.toggle('hide', en[0].isIntersecting); }, { threshold: 0.05 }).observe(box);
  }

  // Control de acceso con lector QR
  var CK = window.CHECKIN;
  if (CK) {
    var res = $('#result'), cin = $('#cin'), call = $('#call'), busy = false, lastCode = '', lastAt = 0, resetT;
    var show = function (level, title, text) {
      res.className = 'result ' + level; res.innerHTML = '<b></b><span></span>';
      res.firstChild.textContent = title; res.lastChild.textContent = text;
      if (navigator.vibrate && navigator.userActivation && navigator.userActivation.hasBeenActive) navigator.vibrate(level === 'ok' ? 80 : [80, 60, 80]);
      clearTimeout(resetT);
      resetT = setTimeout(function () { res.className = 'result idle'; res.innerHTML = '<b>Listo para leer</b><span>Apunta la cámara al código QR de la entrada</span>'; }, 6000);
    };
    var send = function (code, undo) {
      if (busy) return; busy = true;
      var body = new URLSearchParams(); body.set('code', code); body.set('_csrf', window.CSRF);
      fetch(CK.url + (undo ? '&do=undo' : ''), { method: 'POST', body: body, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (j) {
          show(j.level, j.title, j.text);
          if (j.counts) { cin.textContent = j.counts.in; call.textContent = j.counts.all; }
          var clean = code.replace(/^.*\/c\//, '').replace(/[^A-Za-z0-9]/g, '').toUpperCase();
          var row = $('#att tr[data-code="' + clean + '"]');
          if (row && j.ok) { row.classList.toggle('in', !undo); var b = $('.act', row); if (b) b.textContent = undo ? 'Dar entrada' : 'Deshacer'; }
        })
        .catch(function () { show('err', 'Sin conexión', 'No se ha podido validar. Comprueba la cobertura e inténtalo de nuevo.'); })
        .then(function () { busy = false; });
    };
    var manual = $('#manual');
    if (manual) manual.addEventListener('submit', function (e) { e.preventDefault(); var i = $('#code'); if (i.value.trim()) send(i.value.trim()); i.value = ''; });
    $$('#att .act').forEach(function (b) { b.addEventListener('click', function () { var tr = b.closest('tr'); send(tr.dataset.code, tr.classList.contains('in')); }); });
    var filter = $('#filter');
    if (filter) filter.addEventListener('input', function () { var q = filter.value.toLowerCase(); $$('#att tr').forEach(function (tr) { tr.style.display = tr.textContent.toLowerCase().indexOf(q) >= 0 ? '' : 'none'; }); });
    if (CK.code && manual) send(CK.code);

    var start = $('#startcam'), video = $('#cam'), canvas = $('#cv');
    var onCode = function (txt) { var now = Date.now(); if (txt === lastCode && now - lastAt < 4000) return; lastCode = txt; lastAt = now; send(txt); };
    var loop = function (detector) {
      if (video.readyState >= 2 && !busy) {
        if (detector) detector.detect(video).then(function (c) { if (c.length) onCode(c[0].rawValue); }).catch(function () {});
        else if (window.jsQR) {
          var w = video.videoWidth, h = video.videoHeight, s = Math.min(1, 640 / Math.max(w, h));
          canvas.width = w * s; canvas.height = h * s;
          var ctx = canvas.getContext('2d', { willReadFrequently: true }); ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
          var r = window.jsQR(ctx.getImageData(0, 0, canvas.width, canvas.height).data, canvas.width, canvas.height, { inversionAttempts: 'dontInvert' });
          if (r && r.data) onCode(r.data);
        }
      }
      setTimeout(function () { loop(detector); }, 220);
    };
    if (start) start.addEventListener('click', function () {
      if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) { show('err', 'Cámara no disponible', 'Este navegador no permite usar la cámara. Escribe el código a mano.'); return; }
      navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false }).then(function (stream) {
        video.srcObject = stream; video.style.display = 'block'; video.play(); start.style.display = 'none';
        if ('BarcodeDetector' in window) { try { loop(new window.BarcodeDetector({ formats: ['qr_code'] })); return; } catch (x) {} }
        var sc = document.createElement('script'); sc.src = CK.jsqr; sc.onload = function () { loop(null); }; document.head.appendChild(sc);
      }).catch(function () { show('err', 'Sin permiso de cámara', 'Permite el acceso a la cámara en el navegador o escribe el código a mano.'); });
    });
  }

  // Tutorial guiado: resalta un elemento de la pantalla y lo explica, paso a paso y entre pantallas
  var TOURS = window.TOURS;
  if (TOURS) {
    var KEY = 'velada_tour', tstate = null, here = location.pathname + location.search, hole, card, block, target = null;
    try { tstate = JSON.parse(sessionStorage.getItem(KEY)); } catch (x) {}
    var tsave = function () { try { if (tstate) sessionStorage.setItem(KEY, JSON.stringify(tstate)); else sessionStorage.removeItem(KEY); } catch (x) {} };
    var markDone = function () {
      if (!window.TOUR_AUTO) return; window.TOUR_AUTO = false;
      var b = new URLSearchParams(); b.set('_csrf', window.CSRF); fetch(window.TOUR_DONE, { method: 'POST', body: b, credentials: 'same-origin' }).catch(function () {});
    };
    var tend = function () {
      tstate = null; tsave(); markDone(); document.body.classList.remove('nav', 'touring');
      [hole, card, block].forEach(function (n) { if (n) n.remove(); }); hole = card = block = null;
      window.removeEventListener('resize', place); window.removeEventListener('scroll', place, true); document.removeEventListener('keydown', tkey);
    };
    var tgo = function (d) { tstate.i += d; if (tstate.i < 0) tstate.i = 0; tsave(); tshow(); };
    var tkey = function (e) { if (e.key === 'Escape') tend(); else if (e.key === 'ArrowRight' || e.key === 'Enter') { e.preventDefault(); tgo(1); } else if (e.key === 'ArrowLeft') tgo(-1); };
    var place = function () {
      if (!card) return;
      var vw = window.innerWidth, vh = window.innerHeight, small = vw < 700;
      var r = target ? target.getBoundingClientRect() : null;
      if (r && (r.width === 0 || r.bottom < 0 || r.top > vh)) r = null;
      if (!r) { hole.style.display = 'none'; block.classList.add('dim'); card.style.left = Math.max(12, (vw - card.offsetWidth) / 2) + 'px'; card.style.top = Math.max(12, (vh - card.offsetHeight) / 2) + 'px'; return; }
      block.classList.remove('dim'); hole.style.display = 'block';
      var pad = 6, top = Math.max(4, r.top - pad), left = Math.max(4, r.left - pad), w = Math.min(vw - left - 4, r.width + pad * 2), h = Math.min(vh - top - 4, r.height + pad * 2);
      hole.style.top = top + 'px'; hole.style.left = left + 'px'; hole.style.width = w + 'px'; hole.style.height = h + 'px';
      var cw = card.offsetWidth, ch = card.offsetHeight, x, y;
      if (small) { x = (vw - cw) / 2; y = (top + h / 2 > vh / 2) ? 12 : vh - ch - 12; }
      else if (target.closest('#side') && left + w + 14 + cw < vw) { x = left + w + 14; y = Math.min(Math.max(12, top), vh - ch - 12); }
      else { x = Math.min(Math.max(12, left), vw - cw - 12); y = top + h + 12; if (y + ch > vh - 12) y = top - ch - 12; if (y < 12) { y = vh - ch - 12; x = vw - cw - 12; } }
      card.style.left = x + 'px'; card.style.top = y + 'px';
    };
    var tshow = function () {
      var t = tstate && TOURS[tstate.id]; if (!t) { tend(); return; }
      var st = t.steps[tstate.i]; if (!st) { tend(); return; }
      if (st.url && st.url !== here) { tsave(); location.href = st.url; return; }
      if (!card) {
        block = document.createElement('div'); block.className = 'tour-block';
        hole = document.createElement('div'); hole.className = 'tour-hole';
        card = document.createElement('div'); card.className = 'tour-card'; card.setAttribute('role', 'dialog'); card.setAttribute('aria-live', 'polite');
        document.body.appendChild(block); document.body.appendChild(hole); document.body.appendChild(card); document.body.classList.add('touring');
        window.addEventListener('resize', place); window.addEventListener('scroll', place, true); document.addEventListener('keydown', tkey);
      }
      target = st.sel ? document.querySelector(st.sel) : null;
      document.body.classList.toggle('nav', !!(target && target.closest('#side') && window.innerWidth <= 900));
      if (target) { try { target.scrollIntoView({ block: target.closest('#side') ? 'nearest' : 'center', behavior: 'auto' }); } catch (x) { target.scrollIntoView(); } }
      var last = tstate.i === t.steps.length - 1;
      card.innerHTML = '<div class="tour-top"><span></span><button type="button" class="tour-x" aria-label="Cerrar tutorial">Saltar</button></div><h3></h3><p></p><div class="tour-foot"><button type="button" class="btn sm tour-prev">Anterior</button><button type="button" class="btn sm primary tour-next"></button></div>';
      card.querySelector('.tour-top span').textContent = t.title + ' · ' + (tstate.i + 1) + ' de ' + t.steps.length;
      card.querySelector('h3').textContent = st.title; card.querySelector('p').textContent = st.text;
      card.querySelector('.tour-next').textContent = last ? 'Terminar' : 'Siguiente';
      card.querySelector('.tour-prev').style.visibility = tstate.i ? 'visible' : 'hidden';
      card.querySelector('.tour-x').onclick = tend; card.querySelector('.tour-next').onclick = function () { if (last) tend(); else tgo(1); }; card.querySelector('.tour-prev').onclick = function () { tgo(-1); };
      place(); setTimeout(place, 280); card.querySelector('.tour-next').focus({ preventScroll: true });
    };
    document.addEventListener('click', function (e) { var b = e.target.closest('[data-tour]'); if (b) { tstate = { id: b.getAttribute('data-tour'), i: 0 }; tsave(); tshow(); } });
    if (tstate) tshow(); else if (window.TOUR_AUTO && TOURS.general) { tstate = { id: 'general', i: 0 }; tsave(); tshow(); }
  }
})();
