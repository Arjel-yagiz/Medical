<?php
/**
 * Medical AI Agent settings.
 * 1. Create an API key at https://console.anthropic.com  (Settings > API keys)
 * 2. Paste it below. Leave it empty to use the built-in "basic mode" (keyword triage, no internet needed).
 * Never share this file or commit it to GitHub.
 */
define('AI_API_KEY', '');                 // e.g. 'sk-ant-...'
define('AI_MODEL', 'claude-haiku-5-5');   // fast + cheap; use 'claude-sonnet-5-5' for smarter answers
define('AI_SSL_VERIFY', true);            // XAMPP/Windows "SSL certificate problem"? see README_AI.txt (set false ONLY on your own PC)
