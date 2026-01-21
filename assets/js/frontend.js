
(function(){
  function qs(root, sel){ return root.querySelector(sel); }
  function qsa(root, sel){ return Array.prototype.slice.call(root.querySelectorAll(sel)); }
  function currencyFormat(val){ return val; }

  function post(action, data){
    var fd = new FormData();
    fd.append('action', action);
    fd.append('nonce', (window.HR_Ajax && HR_Ajax.nonce) || '');
    Object.keys(data||{}).forEach(function(k){ fd.append(k, data[k]); });
    return fetch((window.HR_Ajax && HR_Ajax.ajax_url)||'/wp-admin/admin-ajax.php', { method:'POST', body: fd, credentials: 'same-origin' })
      .then(function(r){ return r.json(); });
  }

  function getDates(root){
    var ci = (qs(root, '.hr-checkin')||qs(root, 'input[name="checkin"]'));
    var co = (qs(root, '.hr-checkout')||qs(root, 'input[name="checkout"]'));
    return {checkin: ci && ci.value, checkout: co && co.value};
  }

  // Search view event handlers
  document.addEventListener('click', function(e){
    if(e.target && e.target.classList.contains('hr-check-btn')){
      var room = e.target.closest('.hr-room');
      var wrap = e.target.closest('.hr-search');
      var dates = getDates(wrap);
      var status = qs(room, '.hr-availability-status');
      status.textContent = '...';
      post('hr_check_availability', { room_id: room.getAttribute('data-room-id'), checkin: dates.checkin, checkout: dates.checkout })
        .then(function(res){
          if(res && res.success){
            status.textContent = res.data.available ? ('Available — Total ' + res.data.formatted_total) : 'Not available';
          } else {
            status.textContent = (res && res.data && res.data.message) ? res.data.message : 'Error';
          }
        })
        .catch(function(){ status.textContent = 'Network error'; });
    }
    if(e.target && e.target.classList.contains('hr-book-link')){
      e.preventDefault();
      var roomId = e.target.getAttribute('data-room-id');
      // Scroll to booking section if exists
      var booking = document.querySelector('.hr-booking[data-room-id="'+roomId+'"]');
      if(booking){ booking.scrollIntoView({behavior:'smooth'}); }
    }
    if(e.target && e.target.classList.contains('hr-check-availability-btn')){
      var root = e.target.closest('.hr-booking');
      var ci = qs(root, 'input[name="checkin"]').value;
      var co = qs(root, 'input[name="checkout"]').value;
      var status = qs(root, '.hr-status');
      var roomId = root.getAttribute('data-room-id');
      status.textContent = 'Checking...';
      post('hr_check_availability', { room_id: roomId, checkin: ci, checkout: co })
        .then(function(res){
          if(res && res.success){
            status.textContent = res.data.available ? ('Available — Total ' + res.data.formatted_total) : 'Not available';
            qs(root, '.hr-create-booking-btn').disabled = !res.data.available;
          } else {
            status.textContent = (res && res.data && res.data.message) ? res.data.message : 'Error';
          }
        })
        .catch(function(){ status.textContent = 'Network error'; });
    }
    if(e.target && e.target.classList.contains('hr-create-booking-btn')){
      var root = e.target.closest('.hr-booking');
      var data = {
        room_id: root.getAttribute('data-room-id'),
        checkin: qs(root, 'input[name="checkin"]').value,
        checkout: qs(root, 'input[name="checkout"]').value,
        guest_name: qs(root, 'input[name="guest_name"]').value,
        guest_email: qs(root, 'input[name="guest_email"]').value,
        guest_phone: qs(root, 'input[name="guest_phone"]').value
      };
      var status = qs(root, '.hr-status');
      status.textContent = 'Submitting...';
      e.target.disabled = true;
      post('hr_create_booking', data)
        .then(function(res){
          if(res && res.success){
            status.textContent = res.data.message + ' #' + res.data.booking_id + ' – Total ' + res.data.formatted_total;
          } else {
            status.textContent = (res && res.data && res.data.message) ? res.data.message : 'Error';
            e.target.disabled = false;
          }
        })
        .catch(function(){ status.textContent = 'Network error'; e.target.disabled = false; });
    }
  });

  // Auto-update estimated total in booking form
  document.addEventListener('change', function(e){
    if(e.target && (e.target.name === 'checkin' || e.target.name === 'checkout')){
      var root = e.target.closest('.hr-booking');
      if(!root) return;
      var price = parseFloat(qs(root, '.hr-total-amount').getAttribute('data-price')||'0');
      var ci = qs(root, 'input[name="checkin"]').value;
      var co = qs(root, 'input[name="checkout"]').value;
      if(ci && co){
        var nights = Math.max(0, Math.round((new Date(co) - new Date(ci)) / (1000*60*60*24)));
        var total = nights * price;
        if(!isFinite(total)) total = 0;
        qs(root, '.hr-total-amount').textContent = qs(root, '.hr-total-amount').textContent.replace(/[^\d.,₱$€£¥]+/g,'');
        // We rely on server for formatting on check; here show raw approximate
        qs(root, '.hr-total-amount').textContent = total.toFixed(2);
      }
    }
  });
})();
