<?php
// Optional: pass $footerClass = 'footer-dark' for dark-bg pages (default is auto-detect)
$footerClass = $footerClass ?? '';
?>
<footer class="mist-footer <?= $footerClass ?>">
  <div class="mist-footer-main">
    <div class="container">
      <div class="row g-4 g-lg-5">

        <!-- Brand + About -->
        <div class="col-lg-4">
          <div class="mist-footer-brand">
            <img src="/qrepo/assets/mist-logo.svg" alt="MIST" height="48" class="mist-footer-logo">
            <div class="mist-footer-brand-text">
              <div class="mist-footer-brand-name">MIST Question Repository</div>
              <div class="mist-footer-brand-tagline">Academic Question Bank</div>
            </div>
          </div>
          <p class="mist-footer-about">
            Preserving and providing access to examination question papers of the Military Institute of Science and Technology.
          </p>
        </div>

        <!-- Explore -->
        <div class="col-lg-2 col-md-3 col-6">
          <h6 class="mist-footer-heading">Explore</h6>
          <ul class="mist-footer-nav">
            <li><a href="/qrepo/">Home</a></li>
            <li><a href="/qrepo/?browse=1">Browse</a></li>
            <?php if (function_exists('can') && can('upload_assigned')): ?>
            <li><a href="/qrepo/submit.php">Upload</a></li>
            <?php endif; ?>
            <?php if (function_exists('isUserLoggedIn') && isUserLoggedIn()): ?>
            <li><a href="/qrepo/profile.php">My Profile</a></li>
            <?php endif; ?>
          </ul>
        </div>

        <!-- Account -->
        <div class="col-lg-2 col-md-3 col-6">
          <h6 class="mist-footer-heading">Account</h6>
          <ul class="mist-footer-nav">
            <?php if (function_exists('isUserLoggedIn') && isUserLoggedIn()): ?>
            <li><a href="/qrepo/profile.php">Profile</a></li>
            <li><a href="/qrepo/user_logout.php">Sign Out</a></li>
            <?php else: ?>
            <li><a href="/qrepo/login.php">Sign In</a></li>
            <li><a href="/qrepo/register.php">Register</a></li>
            <?php endif; ?>
            <?php if (function_exists('isAdminPanelUser') && isAdminPanelUser()): ?>
            <li><a href="/qrepo/admin/">Admin Panel</a></li>
            <?php endif; ?>
          </ul>
        </div>

        <!-- Contact -->
        <div class="col-lg-4 col-md-6">
          <h6 class="mist-footer-heading">Contact</h6>
          <div class="mist-footer-contact">
            <div class="mist-contact-row">
              <i class="bi bi-geo-alt-fill" aria-hidden="true"></i>
              <span>Mirpur Cantonment, Dhaka-1216, Bangladesh</span>
            </div>
            <div class="mist-contact-row">
              <i class="bi bi-globe" aria-hidden="true"></i>
              <a href="https://mist.ac.bd" target="_blank" rel="noopener">mist.ac.bd</a>
            </div>
            <div class="mist-contact-row">
              <i class="bi bi-envelope-fill" aria-hidden="true"></i>
              <a href="mailto:library@mist.ac.bd">library@mist.ac.bd</a>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>

  <div class="mist-footer-bottom">
    <div class="container">
      <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
        <span>&copy; <?= date('Y') ?> Military Institute of Science and Technology. All rights reserved.</span>
        <span class="mist-footer-powered">Powered by MIST Library</span>
      </div>
    </div>
  </div>
</footer>

<style>
/* ── MIST Footer ─────────────────────────────────────────── */
.mist-footer {
    background: #0d1b2e;
    color: rgba(255,255,255,.75);
    margin-top: auto;
    border-top: 1px solid rgba(201,168,76,.2);
    font-family: 'Segoe UI', system-ui, sans-serif;
}
.mist-footer-main { padding: 3rem 0 2rem; }

.mist-footer-brand {
    display: flex; align-items: center; gap: .85rem; margin-bottom: 1rem;
}
.mist-footer-logo {
    filter: brightness(0) invert(1);
    flex-shrink: 0;
}
.mist-footer-brand-name {
    font-size: 1rem; font-weight: 700; color: #fff; line-height: 1.3;
}
.mist-footer-brand-tagline {
    font-size: .75rem; color: #c9a84c; font-weight: 500; letter-spacing: .4px;
}
.mist-footer-about {
    font-size: .82rem; color: rgba(255,255,255,.5); line-height: 1.7; margin: 0;
}

.mist-footer-heading {
    font-size: .7rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: 1.2px; color: #c9a84c; margin-bottom: .9rem;
}
.mist-footer-nav {
    list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: .45rem;
}
.mist-footer-nav a {
    color: rgba(255,255,255,.55); font-size: .84rem; text-decoration: none;
    transition: color .15s;
}
.mist-footer-nav a:hover { color: #c9a84c; }

.mist-footer-contact { display: flex; flex-direction: column; gap: .6rem; }
.mist-contact-row {
    display: flex; align-items: flex-start; gap: .6rem;
    font-size: .84rem; color: rgba(255,255,255,.55);
}
.mist-contact-row i { color: #c9a84c; margin-top: .15rem; flex-shrink: 0; }
.mist-contact-row a {
    color: rgba(255,255,255,.55); text-decoration: none; transition: color .15s;
}
.mist-contact-row a:hover { color: #c9a84c; }

.mist-footer-bottom {
    border-top: 1px solid rgba(255,255,255,.08);
    padding: 1rem 0;
    font-size: .78rem;
    color: rgba(255,255,255,.35);
}
.mist-footer-powered { color: rgba(201,168,76,.5); font-size: .75rem; }
</style>
