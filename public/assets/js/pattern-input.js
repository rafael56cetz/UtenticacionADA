/**
 * pattern-input.js
 * Componente de cuadrícula 3×3 para captura de patrón de acceso.
 *
 * Reglas del contrato:
 *  - Puntos numerados 1–9 (izquierda-derecha, arriba-abajo)
 *  - Secuencia de 6–9 puntos DISTINTOS
 *  - Sin inserción automática de puntos intermedios
 *  - Entrada por arrastre (mouse/touch), clic individual y teclado
 *  - Limpia la secuencia tras enviar o cancelar
 *
 * Uso:
 *   new PatternInput(containerEl, (sequence) => { ... });
 *   // sequence = [1, 5, 9, ...] cuando el usuario levanta el dedo/mouse
 */

export class PatternInput {
  /**
   * @param {HTMLElement} container — elemento donde se monta el patrón
   * @param {function}    onChange  — callback(sequence: number[]) al soltar
   */
  constructor(container, onChange) {
    this._container = container;
    this._onChange  = onChange;
    this._sequence  = [];
    this._drawing   = false;

    this._render();
    this._attachEvents();
  }

  // ── Render ────────────────────────────────────────────────────────────

  _render() {
    this._container.innerHTML = `
      <p class="pattern-hint">Dibuja tu secuencia (mínimo 6 puntos)</p>
      <div class="pattern-grid" id="pg-grid" role="group" aria-label="Cuadrícula de patrón">
        ${Array.from({ length: 9 }, (_, i) => `
          <button
            type="button"
            class="pattern-dot"
            data-n="${i + 1}"
            aria-label="Punto ${i + 1}"
            aria-pressed="false"
            tabindex="${i === 0 ? 0 : -1}"
          ></button>
        `).join('')}
        <canvas class="pattern-canvas" aria-hidden="true"></canvas>
      </div>
      <div class="pattern-actions">
        <button type="button" class="btn btn-secondary" id="pg-clear">Borrar</button>
      </div>
    `;

    this._grid   = this._container.querySelector('#pg-grid');
    this._dots   = Array.from(this._grid.querySelectorAll('.pattern-dot'));
    this._canvas = this._grid.querySelector('.pattern-canvas');
    this._ctx    = this._canvas.getContext('2d');

    // Dimensionar canvas al tamaño del grid
    this._resizeCanvas();
    window.addEventListener('resize', () => this._resizeCanvas());

    this._container.querySelector('#pg-clear')
      .addEventListener('click', () => this.clear());
  }

  _resizeCanvas() {
    const rect = this._grid.getBoundingClientRect();
    this._canvas.width  = rect.width  || this._grid.offsetWidth;
    this._canvas.height = rect.height || this._grid.offsetHeight;
    this._drawLines();
  }

  // ── Eventos mouse ─────────────────────────────────────────────────────

  _attachEvents() {
    // Mouse
    this._grid.addEventListener('mousedown',  e => this._onStart(e));
    this._grid.addEventListener('mousemove',  e => this._onMove(e));
    document.addEventListener('mouseup',      e => this._onEnd(e));

    // Touch
    this._grid.addEventListener('touchstart', e => { e.preventDefault(); this._onStart(e.touches[0]); }, { passive: false });
    this._grid.addEventListener('touchmove',  e => { e.preventDefault(); this._onMove(e.touches[0]); },  { passive: false });
    this._grid.addEventListener('touchend',   e => { e.preventDefault(); this._onEnd(e); },              { passive: false });

    // Teclado (flechas + Enter/Espacio para seleccionar punto)
    this._grid.addEventListener('keydown', e => this._onKeydown(e));
  }

  _clientPos(e) {
    return { x: e.clientX, y: e.clientY };
  }

  _dotAtPos(x, y) {
    return this._dots.find(d => {
      const r = d.getBoundingClientRect();
      return x >= r.left && x <= r.right && y >= r.top && y <= r.bottom;
    }) ?? null;
  }

  _onStart(e) {
    this._drawing = true;
    const { x, y } = this._clientPos(e);
    const dot = this._dotAtPos(x, y);
    if (dot) this._selectDot(dot);
  }

  _onMove(e) {
    if (!this._drawing) return;
    const { x, y } = this._clientPos(e);
    const dot = this._dotAtPos(x, y);
    if (dot) this._selectDot(dot);
    this._drawLines(x, y); // línea dinámica al cursor
  }

  _onEnd(_e) {
    if (!this._drawing) return;
    this._drawing = false;
    this._drawLines(); // re-dibujar sin línea al cursor
    if (typeof this._onChange === 'function') {
      this._onChange([...this._sequence]);
    }
  }

  // ── Teclado ───────────────────────────────────────────────────────────

  _onKeydown(e) {
    const focused = document.activeElement;
    if (!focused || !focused.classList.contains('pattern-dot')) return;

    const idx = parseInt(focused.dataset.n, 10) - 1; // 0-based
    let next = -1;

    switch (e.key) {
      case 'ArrowRight': next = idx + 1 <= 8 && (idx + 1) % 3 !== 0 ? idx + 1 : idx; break;
      case 'ArrowLeft':  next = idx - 1 >= 0 && idx % 3 !== 0       ? idx - 1 : idx; break;
      case 'ArrowDown':  next = idx + 3 <= 8 ? idx + 3 : idx; break;
      case 'ArrowUp':    next = idx - 3 >= 0 ? idx - 3 : idx; break;
      case 'Enter':
      case ' ':
        e.preventDefault();
        this._selectDot(focused);
        if (typeof this._onChange === 'function') this._onChange([...this._sequence]);
        return;
      default: return;
    }

    e.preventDefault();
    if (next !== idx) {
      this._dots[next].focus();
      this._dots.forEach(d => d.setAttribute('tabindex', '-1'));
      this._dots[next].setAttribute('tabindex', '0');
    }
  }

  // ── Selección de punto ────────────────────────────────────────────────

  _selectDot(dot) {
    const n = parseInt(dot.dataset.n, 10);
    if (this._sequence.includes(n)) return; // ya seleccionado
    if (this._sequence.length >= 9) return;  // máximo alcanzado

    this._sequence.push(n);
    dot.classList.add('selected');
    dot.setAttribute('aria-pressed', 'true');
    this._drawLines();
  }

  // ── Canvas: líneas de conexión ────────────────────────────────────────

  _drawLines(cursorX, cursorY) {
    const canvas = this._canvas;
    const ctx    = this._ctx;
    ctx.clearRect(0, 0, canvas.width, canvas.height);

    if (this._sequence.length < 1) return;

    ctx.strokeStyle = 'rgba(23, 78, 166, 0.55)';
    ctx.lineWidth   = 3;
    ctx.lineCap     = 'round';
    ctx.lineJoin    = 'round';

    const centers = this._sequence.map(n => this._dotCenter(n));

    ctx.beginPath();
    ctx.moveTo(centers[0].x, centers[0].y);
    for (let i = 1; i < centers.length; i++) {
      ctx.lineTo(centers[i].x, centers[i].y);
    }

    // Línea dinámica hacia el cursor mientras arrastra
    if (this._drawing && cursorX !== undefined) {
      const gridRect = this._grid.getBoundingClientRect();
      ctx.lineTo(cursorX - gridRect.left, cursorY - gridRect.top);
    }

    ctx.stroke();
  }

  /** Centro de un punto en coordenadas del canvas */
  _dotCenter(n) {
    const dot  = this._dots[n - 1];
    const dotR = dot.getBoundingClientRect();
    const gridR = this._grid.getBoundingClientRect();
    return {
      x: dotR.left - gridR.left + dotR.width  / 2,
      y: dotR.top  - gridR.top  + dotR.height / 2,
    };
  }

  // ── API pública ───────────────────────────────────────────────────────

  /** Limpia la secuencia y el estado visual */
  clear() {
    this._sequence = [];
    this._drawing  = false;
    this._dots.forEach(d => {
      d.classList.remove('selected');
      d.setAttribute('aria-pressed', 'false');
    });
    this._ctx.clearRect(0, 0, this._canvas.width, this._canvas.height);
    if (typeof this._onChange === 'function') {
      this._onChange([]);
    }
  }

  get sequence() { return [...this._sequence]; }
}
