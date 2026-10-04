/**
 * UIU Friend's Loan & Crowdfunding — front-end enhancements
 * (progressive enhancement only — every page works with JS disabled)
 */
document.addEventListener('DOMContentLoaded', function () {

  /* Auto-dismiss success alerts after a few seconds */
  document.querySelectorAll('.alert-success').forEach(function (el) {
    setTimeout(function () {
      el.style.transition = 'opacity .4s ease';
      el.style.opacity = '0';
      setTimeout(function () { el.remove(); }, 400);
    }, 4500);
  });

  /* Confirm destructive admin/report actions before submitting */
  document.querySelectorAll('form').forEach(function (form) {
    var isDanger = form.querySelector('input[name="remove"], input[name="reject"], input[name="toggle_status"], input[name="report"]');
    if (isDanger) {
      form.addEventListener('submit', function (e) {
        var msg = 'Are you sure you want to do this? This action may be hard to undo.';
        if (!confirm(msg)) e.preventDefault();
      });
    }
  });

  /* Auto-scroll chat thread to the latest message */
  var chatBody = document.querySelector('.chat-body');
  if (chatBody) chatBody.scrollTop = chatBody.scrollHeight;

  /* Keep the custom donation amount input in sync with quick-pick buttons */
  document.querySelectorAll('.toggle-group input[name="preset"]').forEach(function (radio) {
    radio.addEventListener('change', function () {
      var amt = document.getElementById('amt');
      if (amt) amt.value = radio.value;
    });
  });

  /* If the "amount" field is typed into manually, unselect the quick-pick presets */
  var amtField = document.getElementById('amt');
  if (amtField) {
    amtField.addEventListener('input', function () {
      document.querySelectorAll('.toggle-group input[name="preset"]').forEach(function (r) { r.checked = false; });
    });
  }

  /* Campaign credentials: "Add another document" clones a row (max 10) */
  var addDoc = document.getElementById('add-doc-row');
  var docRows = document.getElementById('doc-rows');
  if (addDoc && docRows) {
    addDoc.addEventListener('click', function () {
      if (docRows.querySelectorAll('.doc-row').length >= 10) { alert('You can add up to 10 documents at once. You can add more later.'); return; }
      var row = docRows.querySelector('.doc-row').cloneNode(true);
      row.querySelector('select').value = '';
      var file = row.querySelector('input[type=file]');
      file.value = '';
      var rm = document.createElement('button');
      rm.type = 'button'; rm.className = 'doc-remove'; rm.title = 'Remove'; rm.innerHTML = '&times;';
      rm.addEventListener('click', function () { row.remove(); });
      row.appendChild(rm);
      docRows.appendChild(row);
    });
  }

  /* Simple client-side password confirmation check on registration */
  var confirmField = document.querySelector('input[name="confirm_password"]');
  if (confirmField) {
    var form = confirmField.closest('form');
    form.addEventListener('submit', function (e) {
      var pw = form.querySelector('input[name="password"]').value;
      if (pw !== confirmField.value) {
        e.preventDefault();
        alert('Passwords do not match.');
      }
    });
  }
});
