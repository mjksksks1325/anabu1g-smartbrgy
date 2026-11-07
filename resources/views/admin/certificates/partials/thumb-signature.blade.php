{{--
    Blank right thumb mark box and the resident's signature line, marked by hand at release. The name line is placed at the
    template's height ($referenceTop, in mm from the top of the page) unless longer details push it lower.
--}}
<div class="thumb-mark">
    <div class="thumb-box" aria-hidden="true"></div>
    <span class="thumb-label">Right Thumb Mark</span>
</div>
<p class="resident-signature" data-reference-top="{{ $referenceTop }}"><strong>{{ $signatureName }}</strong><span>Signature over Printed Name</span></p>
