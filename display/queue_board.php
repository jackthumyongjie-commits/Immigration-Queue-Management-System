<?php
require_once dirname(__DIR__) . '/includes/functions.php';

if (isset($_GET['ajax'])) {
    $pdo = db();
    $serving = $pdo->query("SELECT qt.queue_number, qt.visitor_name, qt.called_at, qt.started_at, qt.recall_count, s.name AS service_name, c.counter_number, c.counter_name
        FROM queue_tickets qt
        JOIN services s ON s.id = qt.service_id
        LEFT JOIN counters c ON c.id = qt.counter_id
        WHERE qt.queue_date = CURDATE() AND qt.status = 'serving'
        ORDER BY qt.called_at DESC")->fetchAll();

    $waiting = $pdo->query("SELECT qt.queue_number, qt.visitor_name, s.name AS service_name
        FROM queue_tickets qt JOIN services s ON s.id = qt.service_id
        WHERE qt.queue_date = CURDATE() AND qt.status = 'waiting'
        ORDER BY qt.created_at ASC LIMIT 12")->fetchAll();

    $completed = $pdo->query("SELECT qt.queue_number, c.counter_number, s.name AS service_name
        FROM queue_tickets qt
        JOIN services s ON s.id = qt.service_id
        LEFT JOIN counters c ON c.id = qt.counter_id
        WHERE qt.queue_date = CURDATE() AND qt.status = 'completed'
        ORDER BY qt.completed_at DESC LIMIT 8")->fetchAll();

    $counters = $pdo->query("SELECT c.counter_number, c.counter_name, c.status,
        (SELECT qt.queue_number FROM queue_tickets qt WHERE qt.counter_id=c.id AND qt.status='serving' AND qt.queue_date=CURDATE() LIMIT 1) AS serving_number
        FROM counters c WHERE c.is_active=1 ORDER BY c.counter_number")->fetchAll();

    json_response([
        'success' => true,
        'serving' => $serving,
        'waiting' => $waiting,
        'completed' => $completed,
        'counters' => $counters,
        'time' => date('h:i:s A'),
        'date' => date('l, d F Y'),
    ]);
}

$pageTitle = 'Public Queue Display';
$bodyClass = 'display-board';
$extraCss = '<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&display=swap" rel="stylesheet">';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="qb-shell">
  <header class="qb-header">
    <div class="qb-brand">
      <div class="qb-brand-icon"><i class="fa-solid fa-building-columns"></i></div>
      <div>
        <h1>Immigration Department</h1>
        <p>Live Queue Display Board</p>
      </div>
    </div>
    <div class="qb-clock-wrap">
      <div class="qb-date" id="boardDate"><?= date('l, d F Y') ?></div>
      <div class="d-flex align-items-center justify-content-end gap-3">
        <button type="button" class="qb-sound-btn is-on" id="soundToggle" title="Toggle call sound">
          <i class="fa-solid fa-volume-high" id="soundIcon"></i>
          <span id="soundLabel">Sound On</span>
        </button>
        <div class="qb-clock" id="clock"><?= date('h:i:s A') ?></div>
      </div>
    </div>
  </header>
  <div class="qb-sound-hint" id="soundHint">Click anywhere once to enable call announcements</div>

  <div class="qb-grid">
    <section class="qb-main">
      <div class="qb-serving is-idle" id="servingPanel">
        <div class="qb-serving-label" id="servingLabel" hidden>
          <span class="qb-pulse"></span>
          <span id="servingLabelText">NOW SERVING</span>
        </div>
        <div id="mainServing">
          <div class="qb-serving-number is-idle">---</div>
          <div class="qb-serving-meta">Please wait for the next call</div>
        </div>
      </div>

      <div class="qb-counters-head">
        <h2>Service Counters</h2>
        <span class="qb-live-pill"><i class="fa-solid fa-circle"></i> Live</span>
      </div>
      <div class="qb-counters" id="counterGrid"></div>
    </section>

    <aside class="qb-side">
      <div class="qb-panel">
        <div class="qb-panel-head">
          <i class="fa-solid fa-users-line"></i>
          <span>Next Visitors</span>
        </div>
        <div class="qb-panel-body" id="waitingList"></div>
      </div>
      <div class="qb-panel qb-panel-done">
        <div class="qb-panel-head">
          <i class="fa-solid fa-circle-check"></i>
          <span>Recently Completed</span>
        </div>
        <div class="qb-panel-body" id="completedList"></div>
      </div>
    </aside>
  </div>
</div>
<?php
$extraJs = <<<'JS'
<script>
let lastDisplayKey = '';
let lastAnnounceKey = '';
let boardReady = false;
let soundEnabled = localStorage.getItem('iqms_sound') !== 'off';
let femaleVoice = null;

function esc(v){
  return String(v ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
}

function statusClass(status){
  if(status === 'serving') return 'is-serving';
  if(status === 'available') return 'is-available';
  return 'is-offline';
}

function updateSoundUI(){
  const btn = document.getElementById('soundToggle');
  const icon = document.getElementById('soundIcon');
  const label = document.getElementById('soundLabel');
  if(!btn) return;
  btn.classList.toggle('is-on', soundEnabled);
  btn.classList.toggle('is-off', !soundEnabled);
  icon.className = soundEnabled ? 'fa-solid fa-volume-high' : 'fa-solid fa-volume-xmark';
  label.textContent = soundEnabled ? 'Sound On' : 'Sound Off';
}

function unlockAudio(){
  const hint = document.getElementById('soundHint');
  if(hint) hint.classList.add('is-hidden');
  if('speechSynthesis' in window){
    window.speechSynthesis.getVoices();
  }
}

function pickFemaleVoice(){
  if(femaleVoice) return femaleVoice;
  const voices = window.speechSynthesis.getVoices() || [];
  const preferNames = [/zira/i, /samantha/i, /susan/i, /hazel/i, /karen/i, /moira/i, /female/i, /google uk english female/i];
  for (const re of preferNames) {
    const hit = voices.find(v => /^en/i.test(v.lang) && re.test(v.name));
    if (hit) { femaleVoice = hit; return femaleVoice; }
  }
  const english = voices.filter(v => /^en/i.test(v.lang));
  femaleVoice = english.find(v => !/male|david|mark|daniel|george|thomas|james|david/i.test(v.name)) || null;
  return femaleVoice;
}

function speakNumber(queueNumber, counterNumber){
  if(!soundEnabled || !('speechSynthesis' in window)) return;
  const spelled = String(queueNumber || '').split('').join(' ');
  const counter = counterNumber ? String(counterNumber).split('').join(' ') : '';
  const text = counter
    ? `Now serving number ${spelled}. Please proceed to counter ${counter}.`
    : `Now serving number ${spelled}.`;
  window.speechSynthesis.cancel();
  const utter = new SpeechSynthesisUtterance(text);
  utter.lang = 'en-US';
  utter.rate = 0.92;
  utter.pitch = 1.08;
  utter.volume = 1;
  const preferred = pickFemaleVoice();
  if(preferred) utter.voice = preferred;
  window.speechSynthesis.speak(utter);
}

// Announce ONLY when Start Serving or Recall — not on Call Next
function maybeAnnounce(ticket){
  if(!soundEnabled || !boardReady || !ticket) return;
  const started = !!ticket.started_at;
  const recalls = Number(ticket.recall_count || 0);
  // Call Next only (not started, no recall): silent
  if(!started && recalls === 0) return;
  const key = ticket.queue_number + '|' + (ticket.counter_number || '') + '|' + (ticket.started_at || '') + '|' + recalls;
  if(key === lastAnnounceKey) return;
  lastAnnounceKey = key;
  speakNumber(ticket.queue_number, ticket.counter_number);
}

function tickClock(){
  const now = new Date();
  let h = now.getHours();
  const m = String(now.getMinutes()).padStart(2, '0');
  const s = String(now.getSeconds()).padStart(2, '0');
  const ampm = h >= 12 ? 'PM' : 'AM';
  h = h % 12;
  if(h === 0) h = 12;
  document.getElementById('clock').textContent = String(h).padStart(2, '0') + ':' + m + ':' + s + ' ' + ampm;
  document.getElementById('boardDate').textContent = now.toLocaleDateString('en-GB', {
    weekday: 'long', day: 'numeric', month: 'long', year: 'numeric'
  });
}

async function refreshBoard(){
  try{
    const res = await fetch(window.location.pathname + '?ajax=1');
    const data = await res.json();
    if(!data.success) return;

    const main = document.getElementById('mainServing');
    const panel = document.getElementById('servingPanel');
    const label = document.getElementById('servingLabel');
    const labelText = document.getElementById('servingLabelText');
    // Show as soon as counter Call Next / Recall (status=serving)
    const called = data.serving[0] || null;
    const started = !!(called && called.started_at);

    if(called){
      const displayKey = called.queue_number + '|' + (called.counter_number || '') + '|' + (called.called_at || '') + '|' + (called.recall_count || 0) + '|' + (called.started_at || '');
      label.hidden = false;
      labelText.textContent = started ? 'NOW SERVING' : 'NOW CALLING';
      panel.classList.remove('is-idle');
      panel.classList.toggle('is-active', started);
      panel.classList.toggle('is-calling', !started);
      main.innerHTML = `
        <div class="qb-serving-number">${esc(called.queue_number)}</div>
        <div class="qb-serving-counter">Counter ${esc(called.counter_number || '-')}</div>
        <div class="qb-serving-meta">${esc(called.service_name)}</div>
        <div class="qb-serving-visitor"><i class="fa-solid fa-user"></i> ${esc(called.visitor_name)}</div>`;

      if(displayKey !== lastDisplayKey){
        panel.classList.remove('qb-flash');
        void panel.offsetWidth;
        panel.classList.add('qb-flash');
        maybeAnnounce(called);
        lastDisplayKey = displayKey;
      }
    } else {
      label.hidden = true;
      panel.classList.add('is-idle');
      panel.classList.remove('is-active', 'is-calling');
      main.innerHTML = `
        <div class="qb-serving-number is-idle">---</div>
        <div class="qb-serving-meta">Please wait for the next call</div>`;
      lastDisplayKey = '';
      lastAnnounceKey = '';
    }
    boardReady = true;

    document.getElementById('counterGrid').innerHTML = data.counters.map(c => `
      <div class="qb-counter ${statusClass(c.status)}">
        <div class="qb-counter-top">
          <span class="qb-counter-code">${esc(c.counter_number)}</span>
          <span class="qb-counter-status">${esc(c.status)}</span>
        </div>
        <div class="qb-counter-number">${esc(c.serving_number || '--')}</div>
        <div class="qb-counter-name">${esc(c.counter_name || '')}</div>
      </div>`).join('');

    document.getElementById('waitingList').innerHTML = data.waiting.length
      ? data.waiting.map((w,i) => `
          <div class="qb-row ${i===0 ? 'is-next' : ''}">
            <div class="qb-row-left">
              <span class="qb-row-num">${esc(w.queue_number)}</span>
              <span class="qb-row-service">${esc(w.service_name)}</span>
            </div>
            <span class="qb-row-pos">#${i+1}</span>
          </div>`).join('')
      : `<div class="qb-empty">No waiting visitors</div>`;

    document.getElementById('completedList').innerHTML = data.completed.length
      ? data.completed.map(c => `
          <div class="qb-row is-done">
            <div class="qb-row-left">
              <span class="qb-row-num">${esc(c.queue_number)}</span>
              <span class="qb-row-service">${esc(c.service_name)}</span>
            </div>
            <span class="qb-row-pos">${esc(c.counter_number || '')}</span>
          </div>`).join('')
      : `<div class="qb-empty">No completed tickets yet</div>`;
  }catch(e){}
}

document.getElementById('soundToggle').addEventListener('click', (e) => {
  e.stopPropagation();
  soundEnabled = !soundEnabled;
  localStorage.setItem('iqms_sound', soundEnabled ? 'on' : 'off');
  updateSoundUI();
  unlockAudio();
  if(soundEnabled){
    speakNumber('A001', 'C01');
  } else if('speechSynthesis' in window){
    window.speechSynthesis.cancel();
  }
});

document.addEventListener('click', unlockAudio, { once: true });
document.addEventListener('keydown', unlockAudio, { once: true });
if('speechSynthesis' in window){
  window.speechSynthesis.getVoices();
  window.speechSynthesis.onvoiceschanged = () => { femaleVoice = null; pickFemaleVoice(); };
}

updateSoundUI();
tickClock();
setInterval(tickClock, 1000);
refreshBoard();
setInterval(refreshBoard, 2000);
</script>
JS;
require dirname(__DIR__) . '/includes/footer.php';
?>
