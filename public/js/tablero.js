/* ==========================================================================
   Tablero de Control de Asistencia — dibujo de graficos
   Sin librerias: SVG generado a mano contra los datos que entrega el servidor.
   El servidor decide que se mide; este archivo solo decide como se ve.
   ========================================================================== */
(function () {
  'use strict';

  var NS = 'http://www.w3.org/2000/svg';

  /* ------------------------------------------------------------- formato */

  function num(v, d) {
    if (v === null || v === undefined || !isFinite(v)) return '—';
    return v.toLocaleString('es-BO', { minimumFractionDigits: d || 0, maximumFractionDigits: d || 0 });
  }

  var FMT = {
    pct: function (v) { return num(v, 1) + ' %'; },
    pct0: function (v) { return num(v, 0) + ' %'; },
    horas: function (v) { return num(v, 0) + ' h'; },
    entero: function (v) { return num(v, 0); }
  };

  function fmtDe(nombre) { return FMT[nombre] || FMT.entero; }

  function cssv(nombre) {
    return getComputedStyle(document.documentElement).getPropertyValue(nombre).trim();
  }

  function serieColor(i) { return cssv('--s' + (((i - 1) % 4) + 1)); }

  /* ------------------------------------------------------------- helpers */

  function el(nombre, attrs, texto) {
    var n = document.createElementNS(NS, nombre);
    for (var k in attrs) {
      if (attrs[k] !== null && attrs[k] !== undefined) n.setAttribute(k, attrs[k]);
    }
    if (texto !== undefined) n.textContent = texto;
    return n;
  }

  function svg(ancho, alto) {
    var s = el('svg', {
      viewBox: '0 0 ' + ancho + ' ' + alto,
      class: 'plot',
      role: 'img',
      preserveAspectRatio: 'xMidYMid meet'
    });
    s.style.width = '100%';
    s.style.height = 'auto';
    return s;
  }

  /** Escala "bonita": techo redondeado para que las guias caigan en numeros legibles. */
  function techo(max) {
    if (max <= 0) return 1;
    var mag = Math.pow(10, Math.floor(Math.log10(max)));
    var n = max / mag;
    var paso = n <= 1 ? 1 : n <= 2 ? 2 : n <= 5 ? 5 : 10;
    return paso * mag;
  }

  function guias(g, x0, x1, y, lo, hi, fmt, pasos) {
    pasos = pasos || 4;
    for (var i = 0; i <= pasos; i++) {
      var v = lo + (hi - lo) * i / pasos;
      var yy = y(v);
      g.appendChild(el('line', { x1: x0, x2: x1, y1: yy, y2: yy, class: 'rejilla-linea' }));
      g.appendChild(el('text', { x: x0 - 7, y: yy + 4, 'text-anchor': 'end' }, fmt(v)));
    }
  }

  /* --------------------------------------------------------- columnas */

  function columnas(spec) {
    var datos = spec.datos || [];
    var labels = spec.labels || [];
    if (!datos.length) return null;

    var W = 720, H = 260, L = 52, R = 14, T = 16, B = 40;
    var s = svg(W, H);
    var validos = datos.filter(function (v) { return v !== null && isFinite(v); });
    if (!validos.length) return null;

    var lo = spec.lo !== undefined ? spec.lo : Math.min.apply(null, validos);
    var hi = Math.max.apply(null, validos);
    if (spec.ref !== null && spec.ref !== undefined) hi = Math.max(hi, spec.ref);
    if (spec.formato === 'pct') { lo = Math.max(0, Math.floor(lo / 5) * 5 - 5); hi = Math.min(100, Math.ceil(hi / 5) * 5); }
    else { lo = 0; hi = techo(hi); }
    if (hi <= lo) hi = lo + 1;

    var fmt = fmtDe(spec.formato);
    var y = function (v) { return T + (H - T - B) * (1 - (v - lo) / (hi - lo)); };
    var paso = (W - L - R) / datos.length;
    var ancho = Math.max(4, Math.min(38, paso * 0.62));

    guias(s, L, W - R, y, lo, hi, fmt);

    if (spec.ref !== null && spec.ref !== undefined) {
      s.appendChild(el('line', { x1: L, x2: W - R, y1: y(spec.ref), y2: y(spec.ref), class: 'ref' }));
      s.appendChild(el('text', { x: W - R, y: y(spec.ref) - 5, 'text-anchor': 'end' },
        'meta ' + fmt(spec.ref)));
    }

    datos.forEach(function (v, i) {
      if (v === null || !isFinite(v)) return;
      var cx = L + paso * (i + 0.5);
      var yy = y(v);
      var bueno = spec.ref === null || spec.ref === undefined ? null
        : (spec.hiGood ? v >= spec.ref : v <= spec.ref);
      var color = bueno === null ? cssv('--s1') : (bueno ? cssv('--s1') : cssv('--s2'));

      var r = el('rect', {
        x: cx - ancho / 2, y: yy, width: ancho, height: Math.max(1, y(lo) - yy),
        rx: 3, fill: color
      });
      r.appendChild(el('title', {}, (labels[i] || '') + ': ' + fmt(v)));
      s.appendChild(r);

      if (datos.length <= 14) {
        s.appendChild(el('text', { x: cx, y: H - B + 15, 'text-anchor': 'middle' }, labels[i] || ''));
      } else if (i % Math.ceil(datos.length / 10) === 0) {
        s.appendChild(el('text', { x: cx, y: H - B + 15, 'text-anchor': 'middle' }, labels[i] || ''));
      }
    });

    s.appendChild(el('line', { x1: L, x2: W - R, y1: y(lo), y2: y(lo), class: 'eje' }));
    return s;
  }

  /* ----------------------------------------------------------- barras */

  function barras(spec) {
    var datos = (spec.datos || []).filter(function (d) { return d.v !== null && isFinite(d.v); });
    if (!datos.length) return null;

    var fila = 30, W = 720, L = 150, R = 60, T = 8;
    var H = T + datos.length * fila + 16;
    var s = svg(W, H);
    var fmt = fmtDe(spec.formato);

    var hi = Math.max.apply(null, datos.map(function (d) { return d.v; }));
    if (spec.ref !== null && spec.ref !== undefined) hi = Math.max(hi, spec.ref);
    hi = hi <= 0 ? 1 : hi * 1.02;

    var x = function (v) { return L + (W - L - R) * (v / hi); };

    if (spec.ref !== null && spec.ref !== undefined) {
      s.appendChild(el('line', { x1: x(spec.ref), x2: x(spec.ref), y1: T, y2: H - 16, class: 'ref' }));
      if (spec.refLabel) {
        s.appendChild(el('text', { x: x(spec.ref), y: H - 4, 'text-anchor': 'middle' }, spec.refLabel));
      }
    }

    datos.forEach(function (d, i) {
      var y = T + i * fila;
      s.appendChild(el('text', { x: L - 9, y: y + fila / 2 + 4, 'text-anchor': 'end' },
        d.label.length > 20 ? d.label.slice(0, 19) + '…' : d.label));

      var r = el('rect', {
        x: L, y: y + 5, width: Math.max(2, x(d.v) - L), height: fila - 12,
        rx: 3, fill: d.hl ? cssv('--s2') : cssv('--s1')
      });
      r.appendChild(el('title', {}, d.label + ': ' + fmt(d.v) + (d.detalle ? ' — ' + d.detalle : '')));
      s.appendChild(r);

      s.appendChild(el('text', {
        x: x(d.v) + 7, y: y + fila / 2 + 4, class: 'val-lab'
      }, fmt(d.v)));
    });

    return s;
  }

  /* --------------------------------------------------- columnas apiladas */

  function apiladas(spec) {
    var series = spec.series || [];
    var labels = spec.labels || [];
    if (!series.length || !labels.length) return null;

    var W = 720, H = 280, L = 56, R = 14, T = 16, B = 40;
    var s = svg(W, H);
    var fmt = fmtDe(spec.formato);

    var totales = labels.map(function (_, i) {
      return series.reduce(function (a, se) { return a + (se.datos[i] || 0); }, 0);
    });

    var hi = techo(Math.max.apply(null, totales.concat([1])));
    var y = function (v) { return T + (H - T - B) * (1 - v / hi); };
    var paso = (W - L - R) / labels.length;
    var ancho = Math.max(6, Math.min(46, paso * 0.62));

    guias(s, L, W - R, y, 0, hi, fmt);

    labels.forEach(function (lab, i) {
      var cx = L + paso * (i + 0.5);
      var acum = 0;

      series.forEach(function (se) {
        var v = se.datos[i] || 0;
        if (v <= 0) return;
        var y0 = y(acum), y1 = y(acum + v);
        var r = el('rect', {
          x: cx - ancho / 2, y: y1, width: ancho, height: Math.max(1, y0 - y1),
          fill: serieColor(se.serie || 1)
        });
        r.appendChild(el('title', {}, lab + ' · ' + se.nombre + ': ' + fmt(v)));
        s.appendChild(r);
        acum += v;
      });

      s.appendChild(el('text', { x: cx, y: H - B + 15, 'text-anchor': 'middle' }, lab));
      if (totales[i] > 0) {
        s.appendChild(el('text', { x: cx, y: y(totales[i]) - 6, 'text-anchor': 'middle', class: 'val-lab' },
          fmt(totales[i])));
      }
    });

    s.appendChild(el('line', { x1: L, x2: W - R, y1: y(0), y2: y(0), class: 'eje' }));
    return s;
  }

  /* ----------------------------------------------------------- lineas */

  function lineas(spec) {
    var series = spec.series || [];
    var labels = spec.labels || [];
    if (!series.length || labels.length < 2) return null;

    var W = 720, H = 280, L = 52, R = 16, T = 16, B = 40;
    var s = svg(W, H);
    var fmt = fmtDe(spec.formato);

    var todos = [];
    series.forEach(function (se) {
      se.datos.forEach(function (v) { if (v !== null && isFinite(v)) todos.push(v); });
      if (se.ref !== null && se.ref !== undefined) todos.push(se.ref);
    });
    if (!todos.length) return null;

    var lo = Math.max(0, Math.floor(Math.min.apply(null, todos) / 5) * 5 - 5);
    var hi = Math.min(100, Math.ceil(Math.max.apply(null, todos) / 5) * 5);
    if (hi <= lo) hi = lo + 5;

    var x = function (i) { return L + (W - L - R) * (labels.length === 1 ? 0.5 : i / (labels.length - 1)); };
    var y = function (v) { return T + (H - T - B) * (1 - (v - lo) / (hi - lo)); };

    guias(s, L, W - R, y, lo, hi, fmt);

    series.forEach(function (se, si) {
      var color = serieColor(si + 1);

      if (se.ref !== null && se.ref !== undefined) {
        s.appendChild(el('line', {
          x1: L, x2: W - R, y1: y(se.ref), y2: y(se.ref),
          stroke: color, 'stroke-dasharray': '4 3', 'stroke-width': 1, opacity: .45
        }));
      }

      var d = '';
      se.datos.forEach(function (v, i) {
        if (v === null || !isFinite(v)) return;
        d += (d ? ' L' : 'M') + x(i).toFixed(1) + ' ' + y(v).toFixed(1);
      });

      if (d) {
        s.appendChild(el('path', {
          d: d, fill: 'none', stroke: color, 'stroke-width': 2.2,
          'stroke-linejoin': 'round', 'stroke-linecap': 'round'
        }));
      }

      se.datos.forEach(function (v, i) {
        if (v === null || !isFinite(v)) return;
        var c = el('circle', { cx: x(i), cy: y(v), r: 3, fill: color });
        c.appendChild(el('title', {}, labels[i] + ' · ' + se.nombre + ': ' + fmt(v)));
        s.appendChild(c);
      });
    });

    var cada = Math.ceil(labels.length / 10);
    labels.forEach(function (lab, i) {
      if (i % cada === 0 || i === labels.length - 1) {
        s.appendChild(el('text', { x: x(i), y: H - B + 15, 'text-anchor': 'middle' }, lab));
      }
    });

    return s;
  }

  /* ---------------------------------------------------------- reparto */

  function reparto(spec) {
    var datos = (spec.datos || []).filter(function (d) { return d.v > 0; });
    if (!datos.length) return null;

    var total = datos.reduce(function (a, d) { return a + d.v; }, 0);
    var W = 720, H = 54, R = 3;
    var s = svg(W, H);
    var x = 0;

    datos.forEach(function (d) {
      var w = (W * d.v) / total;
      var r = el('rect', { x: x, y: 0, width: Math.max(1, w - 2), height: 26, rx: R, fill: serieColor(d.serie || 1) });
      r.appendChild(el('title', {}, d.label + ': ' + num(d.v) + ' (' + num(100 * d.v / total, 1) + ' %)'));
      s.appendChild(r);

      if (w > 64) {
        s.appendChild(el('text', { x: x + w / 2, y: 44, 'text-anchor': 'middle' },
          d.label + ' · ' + num(100 * d.v / total, 0) + ' %'));
      }

      x += w;
    });

    return s;
  }

  /* -------------------------------------------------------- mapa de calor */

  function heat(spec) {
    var filas = spec.filas || [];
    var labels = spec.labels || [];
    if (!filas.length || !labels.length) return null;

    var celda = 40, altoFila = 30, L = 130, T = 26;
    var W = Math.max(720, L + labels.length * celda + 20);
    var H = T + filas.length * altoFila + 34;
    var s = svg(W, H);

    var min = spec.min, max = spec.max;
    var rango = max - min || 1;
    var escala = ['--seq1', '--seq2', '--seq3', '--seq4', '--seq5', '--seq6', '--seq7'].map(cssv);

    labels.forEach(function (lab, j) {
      s.appendChild(el('text', { x: L + celda * (j + 0.5), y: T - 9, 'text-anchor': 'middle' }, lab));
    });

    filas.forEach(function (f, i) {
      var y = T + i * altoFila;
      s.appendChild(el('text', { x: L - 9, y: y + altoFila / 2 + 4, 'text-anchor': 'end' }, f.nombre));

      f.celdas.forEach(function (c, j) {
        var x = L + celda * j;

        if (c.valor === null) {
          s.appendChild(el('rect', { x: x + 1, y: y + 2, width: celda - 3, height: altoFila - 5, rx: 4,
            fill: 'transparent', stroke: cssv('--grid') }));
          return;
        }

        var t = Math.max(0, Math.min(1, (c.valor - min) / rango));
        var idx = Math.round(t * (escala.length - 1));
        var r = el('rect', { x: x + 1, y: y + 2, width: celda - 3, height: altoFila - 5, rx: 4, fill: escala[idx] });
        r.appendChild(el('title', {}, f.nombre + ' · ' + labels[j] + ': ' + num(c.valor, 1) + ' %'));
        s.appendChild(r);

        s.appendChild(el('text', {
          x: x + celda / 2, y: y + altoFila / 2 + 4, 'text-anchor': 'middle',
          fill: idx >= 4 ? '#fff' : cssv('--ink'), 'font-size': 10
        }, num(c.valor, 0)));
      });
    });

    // Leyenda: de que valor a que valor va el color.
    var ly = H - 14;
    escala.forEach(function (c, i) {
      s.appendChild(el('rect', { x: L + i * 22, y: ly - 9, width: 20, height: 9, fill: c, rx: 2 }));
    });
    s.appendChild(el('text', { x: L - 9, y: ly, 'text-anchor': 'end' }, num(min, 1) + ' %'));
    s.appendChild(el('text', { x: L + escala.length * 22 + 6, y: ly }, num(max, 1) + ' %'));

    return s;
  }

  /* ------------------------------------------------------------- flujo */

  function flujo(spec) {
    var datos = spec.datos || [];
    if (!datos.length) return null;

    var W = 720, H = 220, L = 46, R = 14, T = 14, B = 34;
    var s = svg(W, H);
    var hi = techo(Math.max.apply(null, datos.concat([1])));
    var y = function (v) { return T + (H - T - B) * (1 - v / hi); };
    var x = function (i) { return L + (W - L - R) * (i / (datos.length - 1)); };

    guias(s, L, W - R, y, 0, hi, FMT.entero);

    var d = 'M' + L + ' ' + y(0);
    datos.forEach(function (v, i) { d += ' L' + x(i).toFixed(1) + ' ' + y(v).toFixed(1); });
    d += ' L' + (W - R) + ' ' + y(0) + ' Z';

    s.appendChild(el('path', { d: d, fill: cssv('--s1'), 'fill-opacity': .18 }));

    var linea = '';
    datos.forEach(function (v, i) { linea += (i ? ' L' : 'M') + x(i).toFixed(1) + ' ' + y(v).toFixed(1); });
    s.appendChild(el('path', { d: linea, fill: 'none', stroke: cssv('--s1'), 'stroke-width': 1.6 }));

    // Una marca cada dos horas: el eje completo en minutos no se lee.
    for (var h = 0; h <= 24; h += 2) {
      var i = Math.min(datos.length - 1, h * 12);
      s.appendChild(el('text', { x: x(i), y: H - B + 15, 'text-anchor': 'middle' },
        (h < 10 ? '0' : '') + h + ':00'));
    }

    return s;
  }

  /* ------------------------------------------------------------- montaje */

  var DIBUJO = {
    cols: columnas, columnas: columnas,
    bars: barras, barras: barras,
    apiladas: apiladas,
    lineas: lineas,
    share: reparto, reparto: reparto,
    heat: heat,
    flujo: flujo
  };

  function dibujar(spec) {
    var fn = DIBUJO[spec.tipo];
    if (!fn) return null;
    try { return fn(spec); } catch (e) { return null; }
  }

  function montar(raiz) {
    (raiz || document).querySelectorAll('[data-grafico]').forEach(function (host) {
      if (host.dataset.listo) return;

      var spec;
      try { spec = JSON.parse(host.getAttribute('data-grafico')); } catch (e) { return; }

      var g = dibujar(spec);

      if (g) {
        if (spec.titulo) g.appendChild(el('title', {}, spec.titulo));
        host.appendChild(g);
        if (spec.titulo) g.setAttribute('aria-label', spec.titulo);
      } else {
        var p = document.createElement('p');
        p.className = 'sub';
        p.textContent = 'Sin datos suficientes para este grafico en el alcance actual.';
        host.appendChild(p);
      }

      host.dataset.listo = '1';
    });
  }

  /* --------------------------------------------------------------- tema */

  var CLAVE_TEMA = 'pc.asistencia.tema';

  function aplicarTema(v) {
    document.documentElement.setAttribute('data-theme', v === 'dark' ? 'dark' : 'light');
    try { localStorage.setItem(CLAVE_TEMA, v); } catch (e) { /* navegacion privada */ }

    var b = document.getElementById('btnTema');
    if (b) b.querySelector('span').textContent = v === 'dark' ? 'Tema claro' : 'Tema oscuro';

    // Los colores estan en variables CSS, asi que hay que redibujar.
    document.querySelectorAll('[data-grafico]').forEach(function (h) {
      h.innerHTML = '';
      delete h.dataset.listo;
    });
    montar();
  }

  /* ------------------------------------------------------------- inicio */

  document.addEventListener('DOMContentLoaded', function () {
    var guardado = null;
    try { guardado = localStorage.getItem(CLAVE_TEMA); } catch (e) { /* sin almacenamiento */ }
    document.documentElement.setAttribute('data-theme', guardado === 'dark' ? 'dark' : 'light');

    montar();

    var btnTema = document.getElementById('btnTema');
    if (btnTema) {
      btnTema.querySelector('span').textContent = guardado === 'dark' ? 'Tema claro' : 'Tema oscuro';
      btnTema.addEventListener('click', function () {
        aplicarTema(document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark');
      });
    }

    var btnFiltros = document.getElementById('btnFiltros');
    var panel = document.getElementById('panelFiltros');
    if (btnFiltros && panel) {
      btnFiltros.addEventListener('click', function () {
        var abierto = panel.hidden;
        panel.hidden = !abierto;
        btnFiltros.setAttribute('aria-expanded', abierto ? 'true' : 'false');
      });
    }

    var btnDl = document.getElementById('btnDescargar');
    var menu = document.getElementById('menuDescargar');
    if (btnDl && menu) {
      btnDl.addEventListener('click', function (e) {
        e.stopPropagation();
        menu.hidden = !menu.hidden;
        btnDl.setAttribute('aria-expanded', menu.hidden ? 'false' : 'true');
      });
      document.addEventListener('click', function () { menu.hidden = true; });
      menu.addEventListener('click', function (e) { e.stopPropagation(); });
    }

    var btnPrint = document.getElementById('btnImprimir');
    if (btnPrint) btnPrint.addEventListener('click', function () { window.print(); });
  });

  window.Tablero = { montar: montar, dibujar: dibujar };
})();
