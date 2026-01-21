
(function(){
  function qs(root, sel){ return root.querySelector(sel); }
  function post(action, data){
    var fd = new FormData();
    fd.append('action', action);
    fd.append('nonce', (window.HRT_Ajax && HRT_Ajax.nonce) || '');
    Object.keys(data||{}).forEach(function(k){ fd.append(k, data[k]); });
    return fetch((window.HRT_Ajax && HRT_Ajax.ajax_url)||'/wp-admin/admin-ajax.php', { method:'POST', body: fd, credentials: 'same-origin' })
      .then(function(r){ return r.json(); });
  }

  function getDates(root){
    var ci = (qs(root, '.hrt-checkin')||qs(root, 'input[name="checkin"]'));
    var co = (qs(root, '.hrt-checkout')||qs(root, 'input[name="checkout"]'));
    return {checkin: ci && ci.value, checkout: co && co.value};
  }

  document.addEventListener('click', function(e){
    // List view availability
    if(e.target && e.target.classList.contains('hrt-check-btn')){
      var type = e.target.closest('.hrt-type');
      var wrap = e.target.closest('.hrt-search');
      var dates = getDates(wrap);
      var status = qs(type, '.hrt-availability-status');
      status.textContent = '...';
      post('hrt_check_availability', { room_type_id: type.getAttribute('data-room-type-id'), checkin: dates.checkin, checkout: dates.checkout })
        .then(function(res){
          if(res && res.success){
            status.textContent = res.data.available ? ('Available (' + res.data.remaining + ' left) — Total ' + res.data.formatted_total) : 'Not available';
          } else {
            status.textContent = (res && res.data && res.data.message) ? res.data.message : 'Error';
          }
        })
        .catch(function(){ status.textContent = 'Network error'; });
    }

    // Scroll to booking block
    if(e.target && e.target.classList.contains('hrt-book-link')){
      e.preventDefault();
      var id = e.target.getAttribute('data-room-type-id');
      var booking = document.querySelector('.hrt-booking[data-room-type-id="'+id+'"]');
      if(booking){ booking.scrollIntoView({behavior:'smooth'}); }
    }

    // Booking: check availability
    if(e.target && e.target.classList.contains('hrt-check-availability-btn')){
      var root = e.target.closest('.hrt-booking');
      var ci = qs(root, 'input[name="checkin"]').value;
      var co = qs(root, 'input[name="checkout"]').value;
      var status = qs(root, '.hrt-status');
      var id = root.getAttribute('data-room-type-id');
      status.textContent = 'Checking...';
      post('hrt_check_availability', { room_type_id: id, checkin: ci, checkout: co })
        .then(function(res){
          var payBtn = root.querySelector('.hrt-pay-reserve-btn');
          var noPayBtn = root.querySelector('.hrt-create-booking-btn');
          var payBox = root.querySelector('.hrt-pay-card');
          if(res && res.success && res.data.available){
            status.textContent = 'Available (' + res.data.remaining + ' left) — Total ' + res.data.formatted_total;
            noPayBtn.disabled = false;
            if(HRT_Ajax && HRT_Ajax.payments_enabled && HRT_Ajax.stripe_pk){
              payBtn.style.display = 'inline-block';
              payBtn.disabled = false;
              payBox.style.display = 'block';
              StripeMount.mount(root);
            }
          } else {
            status.textContent = (res && res.data && res.data.message) ? res.data.message : 'Not available';
            noPayBtn.disabled = true;
            if(payBtn){ payBtn.disabled = true; }
          }
        })
        .catch(function(){ status.textContent = 'Network error'; });
    }

    // Offline reserve
    if(e.target && e.target.classList.contains('hrt-create-booking-btn')){
      var root = e.target.closest('.hrt-booking');
      var data = {
        room_type_id: root.getAttribute('data-room-type-id'),
        checkin: qs(root, 'input[name="checkin"]').value,
        checkout: qs(root, 'input[name="checkout"]').value,
        guest_name: qs(root, 'input[name="guest_name"]').value,
        guest_email: qs(root, 'input[name="guest_email"]').value,
        guest_phone: qs(root, 'input[name="guest_phone"]').value
      };
      var status = qs(root, '.hrt-status');
      status.textContent = 'Submitting...';
      e.target.disabled = true;
      post('hrt_create_booking', data)
        .then(function(res){
          if(res && res.success){ status.textContent = res.data.message + ' #' + res.data.booking_id + ' – Total ' + res.data.formatted_total; }
          else { status.textContent = (res && res.data && res.data.message) ? res.data.message : 'Error'; e.target.disabled = false; }
        })
        .catch(function(){ status.textContent = 'Network error'; e.target.disabled = false; });
    }

    // Pay & reserve
    if(e.target && e.target.classList.contains('hrt-pay-reserve-btn')){
      var root = e.target.closest('.hrt-booking');
      var data = {
        room_type_id: root.getAttribute('data-room-type-id'),
        checkin: qs(root, 'input[name="checkin"]').value,
        checkout: qs(root, 'input[name="checkout"]').value,
        guest_name: qs(root, 'input[name="guest_name"]').value,
        guest_email: qs(root, 'input[name="guest_email"]').value,
        guest_phone: qs(root, 'input[name="guest_phone"]').value
      };
      var status = qs(root, '.hrt-status');
      status.textContent = 'Creating payment...';
      e.target.disabled = true;
      post('hrt_create_payment_intent', data)
        .then(function(res){
          if(!(res && res.success)) throw new Error((res && res.data && res.data.message)||'Stripe error');
          return StripeMount.confirm(res.data.client_secret, data)
            .then(function(result){
              if(result.error) throw new Error(result.error.message);
              return post('hrt_mark_booking_paid', { booking_id: res.data.booking_id, payment_intent_id: result.paymentIntent.id });
            });
        })
        .then(function(done){
          if(done && done.success){ status.textContent = done.data.message; }
          else { throw new Error((done && done.data && done.data.message)||'Could not finalize booking'); }
        })
        .catch(function(err){ status.textContent = (err && err.message) ? err.message : 'Payment failed'; e.target.disabled = false; });
    }
  });

  // Stripe helper
  var StripeMount = (function(){
    var stripe = null, elements = null, card = null;
    function ensure(){
      if(!HRT_Ajax || !HRT_Ajax.payments_enabled || !HRT_Ajax.stripe_pk) return null;
      if(!stripe){ stripe = Stripe(HRT_Ajax.stripe_pk); elements = stripe.elements(); }
      if(!card){ card = elements.create('card'); }
      return { stripe: stripe, card: card };
    }
    return {
      mount: function(root){
        var ctx = ensure(); if(!ctx) return;
        var el = root.querySelector('.hrt-card-element');
        if(el && !el.dataset.mounted){ ctx.card.mount(el); el.dataset.mounted='1'; }
      },
      confirm: function(clientSecret, data){
        var ctx = ensure(); if(!ctx) return Promise.reject(new Error('Stripe not ready'));
        return ctx.stripe.confirmCardPayment(clientSecret, {
          payment_method: { card: ctx.card, billing_details: { name: data.guest_name, email: data.guest_email } }
        });
      }
    };
  })();
})();
