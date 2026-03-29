<?php
$isSuccess = isset($_GET['success']) && $_GET['success'] === '1';
$isError = isset($_GET['error']) && $_GET['error'] === '1';
$requestId = trim((string) ($_GET['id'] ?? ''));

$categories = [
    ['slug' => 'employee-search', 'title' => 'Employee Search', 'description' => 'Find a teammate, freelancer, or specialist for your team.'],
    ['slug' => 'investor-search', 'title' => 'Investor Search', 'description' => 'Find an investor, strategic partner, or project funding.'],
    ['slug' => 'event-speaking', 'title' => 'Event Speaking', 'description' => 'Apply to speak, present, or lead a discussion at an event.'],
    ['slug' => 'marketing-materials-sharing', 'title' => 'Marketing Materials Sharing', 'description' => 'Get support with sharing campaign materials across social channels.'],
    ['slug' => 'sales-support', 'title' => 'Sales Support', 'description' => 'Get help with sales strategy, outreach, and pipeline improvement.'],
    ['slug' => 'client-search', 'title' => 'Client Search', 'description' => 'Find new clients and increase qualified demand.'],
    ['slug' => 'other', 'title' => 'Other', 'description' => 'Any request that does not fit the categories above.'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>0100 Community Help</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Red+Hat+Text:wght@400;500;700&family=Sora:wght@700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
</head>
<body class="form-site">
  <div class="app-shell">
    <header class="app-header">
      <div class="header-container">
        <a href="index.php" class="logo text-display text-caps">0100 Community Help</a>

        <nav class="desktop-nav" aria-label="Desktop Navigation">
          <a href="index.php">Home</a>
          <a href="index.php">Submit Request</a>
          <a href="offers.php">Community Offers</a>
          <a href="admin.php">Admin</a>
        </nav>

        <button class="mobile-toggle" id="nav-toggle" aria-expanded="false" aria-label="Toggle navigation">
          <span class="hamburger-line"></span>
          <span class="hamburger-line"></span>
          <span class="hamburger-line"></span>
        </button>
      </div>

      <div class="mobile-nav-panel" id="mobile-nav-panel">
        <nav class="mobile-nav" aria-label="Mobile Navigation">
          <a href="index.php">Home</a>
          <a href="index.php">Submit Request</a>
          <a href="offers.php">Community Offers</a>
          <a href="admin.php">Admin</a>
        </nav>
      </div>
    </header>

    <main class="main-content">
      <?php if ($isSuccess): ?>
        <section class="success-screen">
          <div class="success-card animate-fade-in-up">
            <p class="text-caps text-xs text-muted mb-3">Request submitted</p>
            <h1 class="success-title">Your request is in.</h1>
            <p class="success-subtitle">Thank you. Our community team will review your request and route it to the best person.</p>

            <div class="success-id-box">
              <p class="text-sm text-muted mb-1">Request ID</p>
              <p class="success-id"><?php echo $requestId !== '' ? htmlspecialchars($requestId, ENT_QUOTES, 'UTF-8') : '—'; ?></p>
            </div>

            <p class="text-sm text-muted mb-8">Expected response: within 1-3 business days</p>
            <a class="success-btn" href="index.php">Submit another request</a>
          </div>
        </section>
      <?php else: ?>
        <div class="form-route-shell">
          <aside class="form-left-panel">
            <div class="panel-edge-blob" aria-hidden="true"></div>
            <div class="left-content-top">
              <h1 class="left-title">Need support from the 0100 community?</h1>
              <p class="left-subtitle">Share your request in a clear way. We will route it to the most relevant people from the ecosystem.</p>
            </div>

            <div class="left-content-bottom">
              <div class="text-sm text-muted">
                Need help? <br>
                <a href="mailto:help@0100.vc" class="text-link">help@0100.vc</a>
              </div>
            </div>
          </aside>

          <section class="form-main">
            <header class="form-header">
              <div class="form-header-inner">
                <a href="index.php" class="header-brand text-caps">0100 Community Help</a>
                <div class="step-indicator-wrap"><div class="step-indicator" id="step-indicator"></div></div>
              </div>
            </header>

            <?php if ($isError): ?>
              <div class="submit-error-banner" role="alert">We could not submit your request. Please check required fields and try again.</div>
            <?php endif; ?>

            <form action="submit.php" method="POST" id="request-form" novalidate>
              <input type="hidden" name="full_name" id="hidden-full-name">
              <input type="hidden" name="email" id="hidden-email">
              <input type="hidden" name="organization" id="hidden-organization">
              <input type="hidden" name="role" id="hidden-role">
              <input type="hidden" name="category" id="hidden-category">
              <input type="hidden" name="description" id="hidden-description">
              <input type="hidden" name="title" id="hidden-title">
              <input type="hidden" name="city" id="hidden-city">
              <input type="hidden" name="phone" id="hidden-phone">
              <input type="hidden" name="urgency" id="hidden-urgency">
              <input type="hidden" name="deadline" id="hidden-deadline">
              <input type="hidden" name="budget" id="hidden-budget">
              <input type="hidden" name="help_type" id="hidden-help-type">
              <input type="hidden" name="tags_text" id="hidden-tags-text">

              <div class="form-content" id="form-content">
                <section class="form-step is-active" data-step="1">
                  <div class="step-header">
                    <h2 class="step-title">Step 1: Identity</h2>
                    <p class="step-subtitle">Tell us who you are.</p>
                  </div>

                  <div class="input-group">
                    <label for="name" class="field-label">Full Name *</label>
                    <input id="name" type="text" placeholder="e.g. Jane Doe" autocomplete="name">
                    <p class="field-error" data-error-for="name"></p>
                  </div>

                  <div class="input-group">
                    <label for="email" class="field-label">Email Address *</label>
                    <input id="email" type="email" placeholder="e.g. jane@example.com" autocomplete="email">
                    <p class="field-error" data-error-for="email"></p>
                  </div>

                  <div class="input-group">
                    <label for="organization" class="field-label">Organization</label>
                    <input id="organization" type="text" placeholder="e.g. Acme Ltd" autocomplete="organization">
                    <p class="field-error" data-error-for="organization"></p>
                  </div>

                  <div class="input-group">
                    <label for="city" class="field-label">City *</label>
                    <input id="city" type="text" placeholder="e.g. Bratislava" autocomplete="address-level2">
                    <p class="field-error" data-error-for="city"></p>
                  </div>

                  <div class="input-group">
                    <label for="phone" class="field-label">Phone</label>
                    <input id="phone" type="tel" placeholder="e.g. +421 900 123 456" autocomplete="tel">
                    <p class="field-error" data-error-for="phone"></p>
                  </div>

                  <div class="role-group" role="radiogroup" aria-labelledby="role-label">
                    <div id="role-label" class="field-label">Your Role *</div>
                    <div class="role-buttons">
                      <button type="button" role="radio" aria-checked="false" class="role-button" data-role="Startup">Startup</button>
                      <button type="button" role="radio" aria-checked="false" class="role-button" data-role="Investor">Investor</button>
                      <button type="button" role="radio" aria-checked="false" class="role-button" data-role="Service Provider">Service Provider</button>
                      <button type="button" role="radio" aria-checked="false" class="role-button" data-role="Community Member">Community Member</button>
                    </div>
                    <p class="field-error" data-error-for="role"></p>
                  </div>
                </section>

                <section class="form-step" data-step="2">
                  <div class="step-header">
                    <h2 class="step-title">Step 2: Request</h2>
                    <p class="step-subtitle">Select a category and describe your need.</p>
                  </div>

                  <div class="category-selection" role="radiogroup" aria-labelledby="category-label">
                    <div id="category-label" class="field-label">Request Category *</div>
                    <div class="category-grid">
                      <?php foreach ($categories as $category): ?>
                        <button type="button" role="radio" aria-checked="false" class="category-card" data-category="<?php echo htmlspecialchars($category['title'], ENT_QUOTES, 'UTF-8'); ?>">
                          <span class="category-title"><?php echo htmlspecialchars($category['title'], ENT_QUOTES, 'UTF-8'); ?></span>
                          <span class="category-description"><?php echo htmlspecialchars($category['description'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </button>
                      <?php endforeach; ?>
                    </div>
                    <p class="field-error" data-error-for="category"></p>
                  </div>

                  <div class="input-group">
                    <label for="title" class="field-label">Short Request Title *</label>
                    <input id="title" type="text" placeholder="e.g. Looking for a pre-seed investor">
                    <p class="field-error" data-error-for="title"></p>
                  </div>

                  <div class="input-group">
                    <label for="description" class="field-label">Detailed Description *</label>
                    <textarea id="description" rows="8" placeholder="Describe your request clearly: what you need, who you are looking for, what is already prepared, and the expected result."></textarea>
                    <p class="step-guidance">Categories include employee search, investor search, event speaking, marketing support, sales support, and client search.</p>
                    <div class="char-count-wrap"><span class="char-count"><span id="description-len">0</span> / 2000</span></div>
                    <p class="field-error" data-error-for="description"></p>
                  </div>
                </section>

                <section class="form-step" data-step="3">
                  <div class="step-header">
                    <h2 class="step-title">Step 3: Details</h2>
                    <p class="step-subtitle">Add context so we can route your request faster.</p>
                  </div>

                  <div class="input-group">
                    <label for="deadline" class="field-label">Deadline (optional)</label>
                    <input id="deadline" type="date">
                    <p class="field-error" data-error-for="deadline"></p>
                  </div>

                  <div class="input-group">
                    <label for="budget" class="field-label">Estimated Budget (EUR)</label>
                    <input id="budget" type="number" placeholder="e.g. 5000" min="0">
                    <p class="field-error" data-error-for="budget"></p>
                  </div>

                  <fieldset class="radio-group">
                    <legend class="field-label">Support Type *</legend>
                    <label class="radio-row"><input type="radio" name="help_type" value="volunteer" checked> <span>Volunteer Time</span></label>
                    <label class="radio-row"><input type="radio" name="help_type" value="financial"> <span>Financial Support</span></label>
                    <label class="radio-row"><input type="radio" name="help_type" value="material"> <span>Material/Equipment</span></label>
                    <label class="radio-row"><input type="radio" name="help_type" value="other"> <span>Other</span></label>
                  </fieldset>

                  <div class="input-group">
                    <label for="tag-input" class="field-label">Tags (press Enter to add)</label>
                    <div class="tag-list" id="tag-list"></div>
                    <input id="tag-input" type="text" placeholder="e.g. logistics, startup, b2b">
                    <p class="field-error" data-error-for="tags"></p>
                  </div>

                  <hr class="section-divider">

                  <div class="consent-row">
                    <input type="checkbox" id="consent">
                    <label for="consent">I confirm that the information provided is accurate and I agree with platform terms. *</label>
                  </div>
                  <p class="field-error" data-error-for="consent">You must agree before continuing.</p>
                </section>

                <section class="form-step" data-step="4">
                  <div class="step-header">
                    <h2 class="step-title">Step 4: Review</h2>
                    <p class="step-subtitle">Review details before submission.</p>
                  </div>

                  <div class="review-card">
                    <div class="review-head"><h3>Identity</h3><button type="button" class="review-edit" data-edit-step="1">Edit</button></div>
                    <dl class="review-list" id="review-identity"></dl>
                  </div>

                  <div class="review-card">
                    <div class="review-head"><h3>Request</h3><button type="button" class="review-edit" data-edit-step="2">Edit</button></div>
                    <dl class="review-list" id="review-need"></dl>
                  </div>

                  <div class="review-card">
                    <div class="review-head"><h3>Details</h3><button type="button" class="review-edit" data-edit-step="3">Edit</button></div>
                    <dl class="review-list" id="review-details"></dl>
                  </div>
                </section>
              </div>

              <div class="nav-row" id="standard-nav">
                <button type="button" id="prev-btn" class="btn-secondary-form">Back</button>
                <button type="button" id="next-btn" class="btn-primary-form">Continue</button>
              </div>

              <div class="submit-row" id="submit-nav">
                <button type="submit" id="submit-btn" class="btn-submit">Submit Request</button>
                <button type="button" id="prev-from-submit" class="text-back">Back</button>
              </div>
            </form>
          </section>
        </div>
      <?php endif; ?>
    </main>

    <footer class="app-footer">
      <div class="footer-content">
        <div class="copyright">© 2026 0100 Community Help</div>
        <div class="footer-links">
          <a href="offers.php">Community Offers</a>
          <a href="admin.php">Admin</a>
          <a href="worker.php">Worker</a>
        </div>
      </div>
    </footer>

    <div class="toast-stack" id="toast-stack" aria-live="polite"></div>
  </div>

  <script src="script.js"></script>
</body>
</html>
