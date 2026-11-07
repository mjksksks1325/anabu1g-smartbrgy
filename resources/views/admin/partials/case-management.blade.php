@can('create', App\Models\ResidentRequestRestriction::class)
<div class="modal-overlay" id="modal-request-restriction">
  <div class="modal">
    <div class="modal-header"><div class="modal-title">New document restriction</div><button type="button" class="modal-close" onclick="closeModal('modal-request-restriction')" aria-label="Close">&times;</button></div>
    <p class="card-sub">Save as Pending Review. Review the decision before explicitly activating it. The resident sees only the optional message to the resident and the end and next review dates; never the reason category, the internal reason or a linked case.</p>
    <form onsubmit="saveRestriction(event)">
      <div class="form-group"><label class="form-label" for="restriction-person-search">Find resident</label><input class="form-input" id="restriction-person-search" autocomplete="off" placeholder="Name or resident number" oninput="searchCaseResidents('restriction-person')"/><select class="form-input" id="restriction-person-resident" required onchange="loadRestrictionCases()"><option value="">Select resident</option></select></div>
      <div class="form-group"><label class="form-label" for="restriction-case">Linked case (optional)</label><select class="form-input" id="restriction-case"><option value="">No linked case</option></select><div id="restriction-case-summary" class="card-sub"></div></div>
      <div class="form-group"><label class="form-label" for="restriction-document">Affected document</label><select class="form-input" id="restriction-document"><option value="">All document requests</option>@foreach(App\CertificateType::cases() as $type)<option value="{{ $type->value }}">{{ $type->value }}</option>@endforeach<optgroup label="{{ __('Staff-only certificates') }}">@foreach(App\AdditionalCertificateType::cases() as $type)<option value="{{ $type->value }}">{{ $type->value }}</option>@endforeach</optgroup></select></div>
      <div class="form-group"><label class="form-label" for="restriction-category">Reason category</label><input class="form-input" id="restriction-category" maxlength="100" required/></div>
      <div class="form-group"><label class="form-label" for="restriction-reason">Internal reviewed decision / reason</label><textarea class="form-input" id="restriction-reason" maxlength="5000" required rows="3"></textarea></div>
      <div class="form-group"><label class="form-label" for="restriction-resident-message">Message to the resident (optional)</label><textarea class="form-input" id="restriction-resident-message" name="resident_visible_reason" maxlength="255" rows="2" aria-describedby="restriction-resident-message-help"></textarea><p class="form-hint" id="restriction-resident-message-help">Shown to the resident on the request page. Do not mention incidents, complainants, VAWC or other people.</p></div>
      <div class="form-group"><label class="form-label" for="restriction-start">Starts at</label><input class="form-input" id="restriction-start" type="datetime-local" required/></div>
      <div class="form-group"><label class="form-label" for="restriction-end">Ends at (optional, shown to the resident)</label><input class="form-input" id="restriction-end" type="datetime-local"/></div>
      <div class="form-group"><label class="form-label" for="restriction-next-review">Next review (optional, shown to the resident)</label><input class="form-input" id="restriction-next-review" type="datetime-local"/></div>
      <div class="modal-footer"><button class="btn btn-primary" type="submit">Save Pending Review</button></div>
    </form>
  </div>
</div>
<div class="modal-overlay" id="modal-lift-restriction"><div class="modal">
  <div class="modal-header"><div class="modal-title">Lift restriction</div><button type="button" class="modal-close" onclick="closeModal('modal-lift-restriction')" aria-label="Close">&times;</button></div>
  <form onsubmit="liftRestriction(event)"><input type="hidden" id="lift-restriction-id"/><div class="form-group"><label class="form-label" for="lift-restriction-reason">Reason for lifting</label><textarea class="form-input" id="lift-restriction-reason" maxlength="5000" required></textarea></div><div class="modal-footer confirm-dialog-actions"><button type="button" class="btn" onclick="closeModal('modal-lift-restriction')">Cancel</button><button class="btn btn-primary" type="submit">Lift restriction</button></div></form>
</div></div>
@endcan
@if(auth()->user()->hasAnyPermission(['vawc.submit', 'vawc.update']))
<div class="modal-overlay" id="modal-protection-order"><div class="modal">
  <div class="modal-header"><div class="modal-title">Restricted BPO record</div><button type="button" class="modal-close" onclick="closeModal('modal-protection-order')" aria-label="Close">&times;</button></div>
  <p class="card-sub">Restricted to staff assigned VAWC / BPO access. Record the approved order as supplied by the barangay. Legal validity periods and deadlines require barangay confirmation; no automatic end date is assigned.</p>
  <form onsubmit="saveProtectionOrder(event)">
    <input type="hidden" id="bpo-id"/><input type="hidden" id="bpo-incident-id"/>
    <div class="form-group"><label class="form-label" for="bpo-protected-search">Protected resident (optional)</label><input class="form-input" id="bpo-protected-search" autocomplete="off" placeholder="Name or resident number" oninput="searchCaseResidents('bpo-protected')"/><select class="form-input" id="bpo-protected-resident"><option value="">External person</option></select></div>
    <div class="form-group"><label class="form-label" for="bpo-protected-person">Protected person name (for external person)</label><input class="form-input" id="bpo-protected-person" maxlength="255"/></div>
    <div class="form-group"><label class="form-label" for="bpo-respondent-search">Respondent resident (optional)</label><input class="form-input" id="bpo-respondent-search" autocomplete="off" placeholder="Name or resident number" oninput="searchCaseResidents('bpo-respondent')"/><select class="form-input" id="bpo-respondent-resident"><option value="">External person</option></select></div>
    <div class="form-group"><label class="form-label" for="bpo-respondent">Respondent name (for external person)</label><input class="form-input" id="bpo-respondent" maxlength="255"/></div>
    <div class="form-row"><div class="form-group"><label class="form-label" for="bpo-issued-on">Date issued</label><input class="form-input" id="bpo-issued-on" type="date" required/></div><div class="form-group"><label class="form-label" for="bpo-effective-on">Effective date (optional)</label><input class="form-input" id="bpo-effective-on" type="date"/></div></div>
    <div class="form-group"><label class="form-label" for="bpo-ends-on">End date (optional)</label><input class="form-input" id="bpo-ends-on" type="date"/></div>
    <div class="form-group"><label class="form-label" for="bpo-status">Administrative status label</label><input class="form-input" id="bpo-status" maxlength="30" required/></div>
    <div class="form-group"><label class="form-label" for="bpo-authority">Issuing authority / staff reference</label><input class="form-input" id="bpo-authority" maxlength="255" required/></div>
    <div class="form-group"><label class="form-label" for="bpo-reference">Supporting document reference (optional)</label><input class="form-input" id="bpo-reference" maxlength="255"/></div>
    <div class="form-group"><label class="form-label" for="bpo-remarks">Confidential internal remarks</label><textarea class="form-input" id="bpo-remarks" maxlength="5000" rows="3"></textarea></div>
    <div class="modal-footer"><button class="btn btn-primary" type="submit">Save BPO record</button></div>
  </form>
</div></div>
@endif
