<?php
/** Reusable "upload credential documents" rows. JS (main.js) lets the user add more rows. */
$__types = campaign_doc_types();
?>
<div id="doc-rows">
  <?php for ($__i = 0; $__i < 3; $__i++): ?>
  <div class="doc-row">
    <select name="doc_type[]">
      <option value="">— Document type —</option>
      <?php foreach ($__types as $__k => $__label): ?>
        <option value="<?= e($__k) ?>"><?= e($__label) ?></option>
      <?php endforeach; ?>
    </select>
    <input type="file" name="doc_file[]" accept=".jpg,.jpeg,.png,.pdf">
  </div>
  <?php endfor; ?>
</div>
<button type="button" class="btn btn-outline btn-sm" id="add-doc-row"><i class="fa-solid fa-plus"></i> Add another document</button>
<p style="font-size:12px;color:var(--text-muted);margin:10px 0 0;">JPG, PNG or PDF · max 4 MB each. Documents are private: only you and the admins can open them. Donors only see which credentials were submitted and verified.</p>
