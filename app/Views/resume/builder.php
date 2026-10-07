<?php
$d = $resume['data'] ?? [];
function v($d, $k, $x = '') { return esc($d[$k] ?? $x); }
$photoUri = \App\Libraries\Photo::dataUri($resume['photo_path'] ?? null);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Resume Builder</title>
<link rel="stylesheet" href="<?= base_url('assets/css/resume-maker.css') ?>">
<?= view('partials/adsense_head') ?>
</head>
<body>
<main class="builder-shell">
<section class="editor">
 <div class="topbar"><a class="back" href="<?= site_url('/') ?>">← Templates</a><b><?= strtoupper($paperSize) ?> Resume</b></div>
 <form id="resumeForm" class="js-validate" enctype="multipart/form-data" novalidate>
  <input type="hidden" name="paper_size" value="<?= esc($paperSize) ?>">
  <input type="hidden" name="uuid" value="<?= esc($resume['uuid'] ?? '') ?>">
  <input type="hidden" name="photo_token" id="photoToken" value="">
  <input type="hidden" name="photo_remove" id="photoRemove" value="">

  <h2>Contact Information</h2>
  <div class="photo-field" id="photoField">
   <div class="photo-drop<?= $photoUri ? ' has-photo' : '' ?>" id="photoDrop" tabindex="0" role="button" aria-label="Upload 2x2 photo">
    <img id="photoPreview" alt="" <?= $photoUri ? 'src="' . $photoUri . '"' : '' ?>>
    <div class="photo-empty"><b>2×2</b><span>Add photo</span></div>
    <div class="photo-progress" id="photoProgress" hidden><div class="ring"></div><span id="photoProgressText">0%</span></div>
   </div>
   <div class="photo-info">
    <b>2×2 Photo <i class="req">*</i></b>
    <p class="hint">JPG, PNG or WEBP, up to 8 MB. Drag &amp; drop or click. It is cropped and resized to a 2×2 ID photo automatically.</p>
    <div class="photo-actions">
     <button type="button" class="btn small secondary" id="photoPick"><?= $photoUri ? 'Change photo' : 'Choose photo' ?></button>
     <button type="button" class="btn small ghost" id="photoClear" <?= $photoUri ? '' : 'hidden' ?>>Remove</button>
    </div>
    <small class="field-msg" id="photoMsg" aria-live="polite"></small>
    <input type="file" id="photoInput" accept="image/jpeg,image/png,image/webp" hidden>
   </div>
  </div>
  <div class="grid">
   <div class="field wide"><label for="f_name">Full Name <i class="req">*</i></label><input id="f_name" required name="full_name" autocomplete="name" data-rule="name" maxlength="160" value="<?= v($d, 'full_name') ?>" placeholder="e.g. Juan Dela Cruz"><small class="field-msg" aria-live="polite"></small></div>
   <div class="field wide"><label for="f_street">Street / Barangay <i class="req">*</i></label><input id="f_street" required name="street" maxlength="160" value="<?= v($d, 'street') ?>" placeholder="e.g. Real St., Brgy. San Antonio"><small class="field-msg" aria-live="polite"></small></div>
   <div class="field wide"><label for="f_location">Town / Province <i class="req">*</i></label><input id="f_location" required name="location" maxlength="160" value="<?= v($d, 'location') ?>" placeholder="e.g. Alangalang, Leyte"><small class="field-msg" aria-live="polite"></small></div>
   <div class="field"><label for="f_contact">Contact Number <i class="req">*</i></label><input id="f_contact" required name="contact" type="tel" inputmode="tel" autocomplete="tel" data-rule="phone" maxlength="20" value="<?= v($d, 'contact') ?>" placeholder="0926-000-0000"><small class="field-msg" aria-live="polite"></small></div>
   <div class="field"><label for="f_email">Email <i class="req">*</i></label><input id="f_email" required type="email" name="email" autocomplete="email" data-rule="email" maxlength="190" value="<?= v($d, 'email') ?>" placeholder="juandelacruz@gmail.com"><small class="field-msg" aria-live="polite"></small></div>
  </div>

  <h2>Objective <small>Optional</small></h2>
  <div class="field"><label for="f_objective" class="sr-only">Objective</label><textarea id="f_objective" name="objective" rows="4" maxlength="800" data-rule="objective" data-counter="800" placeholder="e.g. To obtain a position where I can use my skills and grow while contributing to the success of the company."><?= v($d, 'objective') ?></textarea><small class="field-msg" aria-live="polite"></small></div>

  <h2>Personal Data</h2>
  <div class="grid">
   <div class="field"><label for="f_dob">Date of Birth <i class="req">*</i></label><input id="f_dob" required type="date" name="date_of_birth" data-rule="dob" max="<?= date('Y-m-d') ?>" value="<?= v($d, 'date_of_birth') ?>"><small class="field-msg" aria-live="polite"></small></div>
   <div class="field"><label for="f_cit">Citizenship <i class="req">*</i></label><input id="f_cit" required name="citizenship" maxlength="60" value="<?= v($d, 'citizenship', 'Filipino') ?>"><small class="field-msg" aria-live="polite"></small></div>
   <div class="field"><label for="f_sex">Sex <i class="req">*</i></label><select id="f_sex" required name="sex"><option></option><?php foreach (['Male', 'Female', 'Prefer not to say'] as $o): ?><option <?= ($d['sex'] ?? '') === $o ? 'selected' : '' ?>><?= $o ?></option><?php endforeach ?></select><small class="field-msg" aria-live="polite"></small></div>
   <div class="field"><label for="f_civil">Civil Status <i class="req">*</i></label><select id="f_civil" required name="civil_status"><option></option><?php $cs = $d['civil_status'] ?? ''; $opts = ['Single', 'Married', 'Widowed', 'Separated']; if ($cs !== '' && ! in_array($cs, $opts, true)) $opts[] = $cs; foreach ($opts as $o): ?><option <?= $cs === $o ? 'selected' : '' ?>><?= esc($o) ?></option><?php endforeach ?></select><small class="field-msg" aria-live="polite"></small></div>
   <div class="field"><label for="f_height">Height <i class="req">*</i></label><input id="f_height" required name="height" data-rule="height" maxlength="40" value="<?= v($d, 'height') ?>" placeholder="e.g. 5'1&quot; or 155 cm"><small class="field-msg" aria-live="polite"></small></div>
   <div class="field"><label for="f_weight">Weight <i class="req">*</i></label><input id="f_weight" required name="weight" data-rule="weight" maxlength="40" value="<?= v($d, 'weight') ?>" placeholder="e.g. 42 kg"><small class="field-msg" aria-live="polite"></small></div>
   <div class="field wide"><label for="languageEntry">Languages / Dialects <i class="req">*</i></label><div class="tag-input" id="languageTags"><div class="tag-list" id="languageTagList"></div><input id="languageEntry" type="text" placeholder="Type a language, then press Enter or comma"></div><input type="hidden" id="languages" name="languages" required data-label="Languages / Dialects" value="<?= v($d, 'languages') ?>"><small class="field-msg" aria-live="polite">Press Enter or comma to add each language.</small></div>
  </div>

  <h2>Skills &amp; Qualifications <small>Optional · max 5</small></h2>
  <p class="section-hint">Leave empty to hide this section from your resume.</p>
  <div id="skillsWrap"></div>
  <button type="button" class="add" data-add="skill">+ Add Skill</button>

  <h2>Educational Attainment <small>At least 1 · max 5</small></h2>
  <div id="educationWrap"></div>
  <button type="button" class="add" data-add="education">+ Add Education</button>

  <h2>Achievements <small>Optional · max 5</small></h2>
  <p class="section-hint">Leave empty to hide this section from your resume.</p>
  <div id="achievementsWrap"></div>
  <button type="button" class="add" data-add="achievement">+ Add Achievement</button>

  <h2>Character References <small>At least 1 · max 5</small></h2>
  <div id="referencesWrap"></div>
  <button type="button" class="add" data-add="reference">+ Add Reference</button>

  <div id="errors" aria-live="assertive"></div>
  <div class="actions">
   <button type="button" id="saveBtn" class="btn secondary">Save</button>
   <button class="btn" type="submit" id="downloadBtn">Save &amp; Download DOCX</button>
  </div>
 </form>
 <?= view('partials/ad', ['slot' => 'slotBuilder']) ?>
</section>
<aside class="preview-pane">
 <div class="preview-head"><b>Live Preview</b><span id="previewStatus">Updates as you type.</span></div>
 <iframe id="previewFrame" title="Resume preview"></iframe>
</aside>
</main>
<div class="toasts" id="toasts" aria-live="polite"></div>
<script>window.RESUME_DATA=<?= json_encode($d, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;window.RESUME_URLS=<?= json_encode(['save' => site_url('resume/save'), 'photo' => site_url('resume/photo'), 'render' => site_url('resume/render')], JSON_UNESCAPED_SLASHES) ?>;</script>
<script src="<?= base_url('assets/js/resume-maker.js') ?>"></script>
</body>
</html>
