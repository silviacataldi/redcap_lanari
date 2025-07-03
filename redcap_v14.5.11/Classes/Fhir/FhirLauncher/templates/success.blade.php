@extends('layout')

@section('title', 'FHIR Token Success')

@section('content')
<div class="border rounded p-5 mx-auto mt-5">
    <h3 style="color:green;"><i class="fas fa-check"></i> Successful login to the EHR</h3>
    <p>Your EHR login was successful: you are now authorized to securely pull data from the EHR system.</p>

    <div class="buttons">
        @if($launchPage)
        <div>
            <div class="progress">
                <div class="progress-bar bg-success" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
            </div>
            <p>You are being automatically redirected back to REDCap.</p>
            <div>
                <a href="{{$launchPage}}" class="btn btn-sm btn-primary text-white"><i class="fas fa-arrow-left"></i> Go back to REDCap</a>
            </div>
        </div>
        @else
            <div>
                <a href="{{$APP_PATH_WEBROOT_FULL}}index.php?action=myprojects"class="btn btn-sm btn-primary text-white"><i class="far fa-list-alt"></i> Go to projects</a>
            </div>
        @endif
    </div>
</div>
<style>
.buttons > div + div {
    margin-top: 3px;
}
.progress {
    /* width: 50%; */
    margin: 10px 0;
}
.container {
    max-width: 50vw;
    min-width: 300px;
    margin: auto;
}
</style>
<script>
(function(document, window){
    /**
     * if a launch page is detected then redirect
     * automatically after X seconds
     */
    function goToPage(page) {
        location.href = page;
    };

    function startTimer(callback, seconds)
    {
        const progress_bar = document.querySelector('.progress .progress-bar');
        let interval_id;
        let total = remaining = seconds || 3; // default to X seconds

        // listen for active animations; run the callback when the animation is over
        progress_bar.addEventListener('animationend', function() {
            console.log('animationend')
        });

        progress_bar.style.width = '0%';
        interval_id = setInterval(function() {
            remaining -= 1;
            var width = 100-(100/total*remaining);
            progress_bar.style.width = width+'%';
            if(width>=100) {
                clearInterval(interval_id);
                if(typeof callback == 'function') {
                    callback()
                }
            }
        }, 1000);
    }

    var launch_page = '{!!$launchPage!!}';
    if( launch_page && (launch_page!=location.href) ) {
        // redirect after X seconds
        startTimer(function() {
            goToPage(launch_page)
        }, 10)
    }

}(document, window))
</script>
@endsection