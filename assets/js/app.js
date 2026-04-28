// IT Weekend Tutorials Hub — app.js

// ── Highlight.js init ────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('pre code').forEach(el => {
        if (typeof hljs !== 'undefined') hljs.highlightElement(el);
    });

    // ── Delete confirmation ──────────────────────────────────────────────────
    document.querySelectorAll('[data-confirm]').forEach(btn => {
        btn.addEventListener('click', e => {
            if (!confirm(btn.dataset.confirm || 'Are you sure?')) {
                e.preventDefault();
            }
        });
    });

    // ── Quiz renderer ────────────────────────────────────────────────────────
    const container = document.getElementById('quiz-container');
    if (!container) return;

    const quiz      = JSON.parse(container.dataset.quiz);
    const lessonId  = parseInt(container.dataset.lessonId, 10);
    const csrf      = container.dataset.csrf;

    if (!quiz || !quiz.questions || quiz.questions.length === 0) {
        container.innerHTML = '<p class="text-muted">No questions in this quiz.</p>';
        return;
    }

    let currentQ = 0;
    let score    = 0;
    let answered = false;

    function render() {
        const q = quiz.questions[currentQ];
        const total = quiz.questions.length;

        container.innerHTML = `
            <div class="mb-3 d-flex justify-content-between align-items-center">
                <span class="text-muted small">Question ${currentQ + 1} of ${total}</span>
                <div class="progress flex-grow-1 mx-3" style="height:6px">
                    <div class="progress-bar bg-primary" style="width:${((currentQ) / total) * 100}%"></div>
                </div>
                <span class="text-muted small">Score: ${score}</span>
            </div>
            <p class="fw-semibold fs-5 mb-4">${escHtml(q.prompt)}</p>
            <div class="d-grid gap-2 mb-4" id="options">
                ${q.options.map((opt, i) => `
                    <button class="btn btn-outline-secondary option-btn" data-index="${i}">
                        <span class="me-2 fw-bold">${String.fromCharCode(65 + i)}.</span>${escHtml(opt)}
                    </button>
                `).join('')}
            </div>
            <div id="feedback" class="mb-3" style="display:none"></div>
            <button id="next-btn" class="btn btn-primary" style="display:none">
                ${currentQ < total - 1 ? 'Next Question →' : 'See Results'}
            </button>
        `;

        // Bind option clicks
        container.querySelectorAll('.option-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                if (answered) return;
                answered = true;
                const idx = parseInt(btn.dataset.index, 10);
                const correct = q.correctIndex;
                const feedback = container.querySelector('#feedback');

                container.querySelectorAll('.option-btn').forEach(b => b.disabled = true);

                if (idx === correct) {
                    score++;
                    btn.classList.add('correct');
                    feedback.innerHTML = `<div class="alert alert-success p-2"><i class="bi bi-check-circle-fill me-2"></i>Correct! ${q.explanation ? escHtml(q.explanation) : ''}</div>`;
                } else {
                    btn.classList.add('wrong');
                    container.querySelectorAll('.option-btn')[correct].classList.add('correct');
                    feedback.innerHTML = `<div class="alert alert-danger p-2"><i class="bi bi-x-circle-fill me-2"></i>Incorrect. ${q.explanation ? escHtml(q.explanation) : ''}</div>`;
                }
                feedback.style.display = '';
                container.querySelector('#next-btn').style.display = '';
            });
        });

        // Next/Results button
        const nextBtn = container.querySelector('#next-btn');
        if (nextBtn) {
            nextBtn.addEventListener('click', () => {
                if (currentQ < quiz.questions.length - 1) {
                    currentQ++;
                    answered = false;
                    render();
                } else {
                    showResults();
                }
            });
        }
    }

    function showResults() {
        const total = quiz.questions.length;
        const pct   = Math.round((score / total) * 100);
        const passed = pct >= 70;

        container.innerHTML = `
            <div class="text-center py-4">
                <div class="display-4 mb-3">${passed ? '🎉' : '📚'}</div>
                <h3 class="fw-bold">${passed ? 'Great job!' : 'Keep practicing!'}</h3>
                <p class="lead">You scored <strong>${score} / ${total}</strong> (${pct}%)</p>
                <div class="progress mx-auto mb-4" style="height:16px; max-width:300px">
                    <div class="progress-bar ${passed ? 'bg-success' : 'bg-warning'}"
                         style="width:${pct}%" role="progressbar">${pct}%</div>
                </div>
                <button class="btn btn-outline-primary me-2" id="retry-btn">Retry Quiz</button>
                <button class="btn btn-secondary" id="close-btn">Close</button>
            </div>
        `;

        // Save score to server
        fetch('/api/progress.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({
                csrf_token: csrf,
                action: 'quiz_score',
                lesson_id: lessonId,
                score: score,
                total: total,
            }),
        }).catch(() => {});

        container.querySelector('#retry-btn').addEventListener('click', () => {
            currentQ = 0; score = 0; answered = false;
            render();
        });
        container.querySelector('#close-btn').addEventListener('click', () => {
            document.getElementById('quiz-section').style.display = 'none';
        });
    }

    render();
});

// ── Mark-complete form: AJAX ─────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    ['complete-form', 'uncomplete-form'].forEach(formId => {
        const form = document.getElementById(formId);
        if (!form) return;
        form.addEventListener('submit', async e => {
            e.preventDefault();
            const data = new URLSearchParams(new FormData(form));
            const res  = await fetch('/api/progress.php', {method:'POST', body: data});
            if (res.ok) {
                location.reload();
            }
        });
    });
});

function escHtml(s) {
    if (!s) return '';
    return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
