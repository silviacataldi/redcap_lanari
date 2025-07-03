@extends('layout')

@section('title', 'Map EHR User')

@section('content')
<div>
    <h3>EHR to REDCap user mapping</h3>
    <p>REDCap would like to map the EHR user to your username, but the EHR user is associated to multiple identifier systems.</p>
    <p>please choose the one you want to map from the list below to proceed.</p> 
    <form id="ehr-type-form" action="" METHOD="POST">
        <label for="fhir-user"></label>
        <input type="hidden" name="skip" value="0">
        <select name="fhir-user" id="fhir-user">
            <option value="" disabled>Please select an option...</option>
            @foreach ($fhirUsers as $type => $value)
                <option value="{{$type}}">{{$value}}</option>
            @endforeach
        </select>
        <button class="btn btn-sm btn-primary" type="submit">Select</button>
        <button class="btn btn-sm btn-secondary" data-type="skip" type="button">Skip</button>
    </form>
</div>
<style>

</style>
<script>
(function(document, window){
   document.addEventListener('DOMContentLoaded', function() {
    // logic for skipping the EHR to REDCap user mapping
    const form = document.querySelector('#ehr-type-form')
    if(!form) return
    const skipButton = form.querySelector('[data-type="skip"]')
    if(!skipButton) return
    skipButton.addEventListener('click', function(e) {
        form['skip'].value = 1
        form.submit()
    })
   })

}(document, window))
</script>
@endsection