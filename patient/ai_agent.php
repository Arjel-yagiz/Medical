<?php
$pageTitle = 'Omega&Care'; $area = 'patient';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/ai.php';
require_patient();
$aiOn = AI_API_KEY !== '';
$first = explode(' ', trim((string) ($_SESSION['user_name'] ?? '')))[0] ?: 'there';
require __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/ai.css">
<div class="ai-shell" id="aiChat" data-api="<?= BASE_URL ?>/patient/api_ai.php" data-csrf="<?= e(csrf_token()) ?>">
  <header class="ai-head">
    <span class="ai-avatar big">Ω</span>
    <div class="ai-title"><b>Omega</b><small><i class="live"></i> Online &middot; <?= $aiOn ? 'AI mode' : 'Basic mode' ?></small></div>
    <button type="button" class="ai-newchat" id="aiNew" title="Start a new conversation">&#8635; New chat</button>
  </header>

  <div class="ai-log" id="aiLog" role="log" aria-live="polite">
    <section class="ai-welcome" id="aiWelcome">
      <span class="ai-avatar hero">Ω</span>
      <h2>Hi <?= e($first) ?>, I&rsquo;m Omega</h2>
      <p>Tell me what you feel or what you need. I can point you to the right doctor and open dates, or just listen when you want to talk, vent or be cheered up.</p>
      <div class="ai-feats">
        <div><span>🩺</span><b>Find the right doctor</b><small>Describe symptoms in English or Filipino</small></div>
        <div><span>📅</span><b>See open dates</b><small>Live slots from the clinic</small></div>
        <div><span>🎟️</span><b>Check your visits</b><small>Ask about upcoming appointments</small></div>
        <div><span>💬</span><b>Talk it out</b><small>Stress, worries or a rough day</small></div>
      </div>
      <div class="ai-chips" id="aiChips">
        <button type="button">I have a fever and cough</button>
        <button type="button">My child has a skin rash</button>
        <button type="button">I need a heart check-up</button>
        <button type="button">Show my upcoming appointments</button>
        <button type="button">Earliest slot in Pediatrics</button>
        <button type="button">I feel stressed and overwhelmed</button>
        <button type="button">Cheer me up</button>
        <button type="button">I can't sleep lately</button>
        <button type="button">I just need to vent</button>
      </div>
    </section>
  </div>

  <form class="ai-composer" id="aiForm" autocomplete="off">
    <textarea id="aiInput" rows="1" maxlength="600" placeholder="Ask about a doctor, or tell me how you feel..." aria-label="Message"></textarea>
    <span class="ai-count" id="aiCount">0/600</span>
    <button class="ai-send" id="aiSend" type="submit" aria-label="Send">&#10148;</button>
  </form>
  <p class="ai-foot">Omega&amp;Care is an AI companion, not a doctor or therapist. It cannot diagnose or prescribe, and chats are not saved. In an emergency or if you feel unsafe, call <b>911</b> (or NCMH <b>1553</b>) or go to the nearest ER.</p>
</div>
<script src="<?= BASE_URL ?>/assets/js/ai.js"></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
