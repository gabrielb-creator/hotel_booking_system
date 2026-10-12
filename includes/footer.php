</main><footer><div class="container footer-grid"><div><div class="brand">◆ LuxeStay</div><p>Comfort, convenience and effortless booking.</p></div><div><strong>Explore</strong><a href="<?=url('rooms/index.php')?>">Rooms</a><a href="<?=url('index.php#about')?>">About</a></div><div><strong>Contact</strong><p>+63 917 000 0000<br>stay@luxestay.test</p></div></div><div class="container copyright">© <?=date('Y')?> LuxeStay Hotel Booking System.</div></footer><div class="confirm-overlay" id="confirmOverlay" aria-hidden="true">
  <section class="confirm-panel" role="dialog" aria-modal="true" aria-labelledby="confirmTitle" aria-describedby="confirmMessage">
    <div class="confirm-icon" id="confirmIcon" aria-hidden="true">!</div>
    <h2 id="confirmTitle">Confirm this action</h2>
    <p id="confirmMessage">Please review this action before continuing.</p>
    <div class="confirm-actions"><button type="button" class="btn btn-outline" id="confirmCancel">Go back</button><button type="button" class="btn btn-primary" id="confirmProceed">Confirm</button></div>
  </section>
</div>
<script src="<?=url('assets/js/app.js')?>"></script></body></html>