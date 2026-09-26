export class PatternInput {
  constructor(container, onComplete = null) {
    this.container = container;
    this.onComplete = onComplete;
    this.sequence = [];
    this.isDrawing = false;
    this.nodes = [];
    this.linesContainer = null;
    
    this.initUI();
    this.bindEvents();
  }

  initUI() {
    this.container.innerHTML = '';
    const grid = document.createElement('div');
    grid.className = 'pattern-grid';
    
    // SVG for lines
    this.linesContainer = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    this.linesContainer.classList.add('pattern-canvas');
    grid.appendChild(this.linesContainer);

    for (let i = 1; i <= 9; i++) {
      const node = document.createElement('div');
      node.className = 'pattern-node';
      node.dataset.value = i;
      node.textContent = i;
      node.tabIndex = 0; // keyboard access
      this.nodes.push(node);
      grid.appendChild(node);
    }
    
    this.container.appendChild(grid);
  }

  bindEvents() {
    const triggerActivate = (node) => {
      const val = parseInt(node.dataset.value, 10);
      if (!this.sequence.includes(val)) {
        this.sequence.push(val);
        node.classList.add('active');
        this.drawLines();
      }
    };

    // Mouse & Touch events
    this.container.addEventListener('pointerdown', (e) => {
      if (e.target.classList.contains('pattern-node')) {
        this.isDrawing = true;
        this.clear();
        triggerActivate(e.target);
      }
    });

    window.addEventListener('pointerup', () => {
      if (this.isDrawing) {
        this.isDrawing = false;
        if (this.sequence.length > 0 && this.onComplete) {
          this.onComplete(this.sequence);
        }
      }
    });

    this.container.addEventListener('pointermove', (e) => {
      if (!this.isDrawing) return;
      const el = document.elementFromPoint(e.clientX, e.clientY);
      if (el && el.classList.contains('pattern-node')) {
        triggerActivate(el);
      }
    });

    // Keyboard events
    this.container.addEventListener('keydown', (e) => {
      if (e.target.classList.contains('pattern-node')) {
        const val = e.key;
        if (val >= '1' && val <= '9') {
          const targetNode = this.nodes[parseInt(val, 10) - 1];
          triggerActivate(targetNode);
        } else if (e.key === 'Enter' || e.key === ' ') {
          triggerActivate(e.target);
        } else if (e.key === 'Escape') {
          this.clear();
        } else if (e.key === 'Backspace') {
          const lastVal = this.sequence.pop();
          if (lastVal) {
            this.nodes[lastVal - 1].classList.remove('active');
            this.drawLines();
          }
        }
      }
    });
  }

  drawLines() {
    this.linesContainer.innerHTML = '';
    if (this.sequence.length < 2) return;
    
    for (let i = 0; i < this.sequence.length - 1; i++) {
      const startNode = this.nodes[this.sequence[i] - 1];
      const endNode = this.nodes[this.sequence[i+1] - 1];
      
      const r1 = startNode.getBoundingClientRect();
      const r2 = endNode.getBoundingClientRect();
      const gridR = this.linesContainer.getBoundingClientRect();
      
      const line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
      line.setAttribute('x1', r1.left + r1.width/2 - gridR.left);
      line.setAttribute('y1', r1.top + r1.height/2 - gridR.top);
      line.setAttribute('x2', r2.left + r2.width/2 - gridR.left);
      line.setAttribute('y2', r2.top + r2.height/2 - gridR.top);
      line.setAttribute('stroke', '#174EA6');
      line.setAttribute('stroke-width', '4');
      this.linesContainer.appendChild(line);
    }
  }

  clear() {
    this.sequence = [];
    this.nodes.forEach(n => n.classList.remove('active'));
    this.linesContainer.innerHTML = '';
  }
}
