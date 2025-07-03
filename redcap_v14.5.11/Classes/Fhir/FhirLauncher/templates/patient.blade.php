@extends('layout')

@section('title', 'Map EHR User')

@section('header')
{!!$navbar!!}
@endsection

@section('footer')
{!!$footer!!}
@endsection

@section('content')

@include('partials.lang')

<style type="text/css">
body {
	font-family: "Open Sans",Helvetica,Arial,sans-serif;
    font-size: 13px;
	padding-top: 50px;
}
</style>

@include('partials.global-js-variables')
@php(loadJS('EHR/PatientConnector.js'))
@php(loadJS('EHR/EHR.js'))

<script>
var connector;
document.addEventListener('DOMContentLoaded', function() {
	var browserSupported = {{ $browser_supported ? 'true' : 'false' }};
	var apiURL = '{{ $patientApiURL }}';
	var csrf_token = '{{ $redcap_csrf_token }}';
	connector = new PatientConnector(browserSupported, apiURL, csrf_token);

	// init the explanation dialog
	$('#ddpExplainDialog').dialog({
		bgiframe: true, modal: true, width: 750,
		open: function(){ fitDialog(this); },
		buttons: {
			Close: function() { $(this).dialog('close'); }
		},
		autoOpen: false
	});
})
</script>

<div class="container-fluid" style="margin-top:10px;">
	@if(!$browser_supported)
	<div class="alert alert-warning">
		<p>Browser not supported.</p>
		<p>The "view patient in browser" feature is disabled.<br>
			To view a patient in the project, you will need to log into REDCap outside of the EHR,
			and open the patient's record there.</p> 
	</div>
	@endif
	<div class="col-12">


		{{-- <redcap-dialog title="Success" >
		hello world
		</redcap-dialog> --}}
		<table class="dataTable cell-border no-footer" style="width:800px;max-width:100%;">
			<thead>
				<tr>
					<th style="background-color: #ddd;border: 1px solid #bbb;">
						<div class="float-start" style="font-size:16px;">{{$lang['data_entry_389']}}</div>
						<div class="float-end" style="font-weight:normal;">
							<select id="unregisteredProjects" class="x-form-text x-form-field" style="max-width:400px;" onchange="connector.addProject(this.value)">
								<option value="" selected="selected">-- {{$lang['data_entry_373']}} --</option>
								{{-- list projects divided in groups --}}
								@foreach($unregisteredProjects as $group=>$projects)
								<optgroup label="{{$group}}">
									@foreach($projects as $pid=>$project)
									<option value="{{$pid}}">{{$project}}</option>
									@endforeach
								</optgroup>
								@endforeach
							</select>
						</div>
					</th>
				</tr>
			</thead>
			<tbody>
			{{-- loop through all projects --}}
			@foreach ($registeredProjects as $project_id=>$attr)
				@php($rowClass = (isset($rowClass) && $rowClass == "even") ? 'odd' : 'even')
				<tr class="{{$rowClass}}">
					<td>
					@if($attr['ddp_enabled'])
						<div class="float-start" style="width:180px;">
						@if(empty($attr['record']))
							{{-- patient is not in project--}}
							@php
							// Disable button if user doesn't have Create Record privileges in the project
							$buttonDisabled = $attr['record_create'] ? '' : 'disabled';
							$buttonTitle = $attr['record_create'] ? $lang['data_entry_376'] : $lang['data_entry_394'];
							@endphp
							<button class="btn btn-success btn-xs" title="{{$buttonTitle}}" {{$buttonDisabled}} onclick="connector.addPatientToProject({{$project_id}},'{{htmlspecialchars( @$patientData->MRN, ENT_QUOTES)}}','{{$attr['record_auto_numbering']}}')"><span class="fas fa-plus"></span>&nbsp;{{$lang['data_entry_376']}}</button>
						@else
							{{-- patient is in project--}}
							<button class="btn btn-primaryrc btn-xs" onclick="connector.showRecord({{$project_id}},'{{$attr['record']}}')"><i class="fas fa-columns"></i> {{$lang['data_entry_377']}}</button>
						@endif

						@if ( ($browser_supported===true) && ($attr['ddp_items'] != '') )
							{{-- Patient record doesn't exist in project --}}
							<div style="display:inline-block;">
							@if($attr['ddp_items'] == '0')
								{{$lang['data_entry_379']}}
							@else
								<a href="{{$APP_PATH_WEBROOT}}DataEntry/record_home.php?pid={{$project_id}}&id={{$attr['record']}}&openDDP=1" style="color:#C00000">
									<span class="badgerc">{{$attr['ddp_items']}} {{$lang['data_entry_378']}}</span>
								</a>
							@endif
							</div>
						@endif
						</div>

						<div class="float-start">
							<div class="ehr-project-title-{{$project_id}}" style="font-size:14px;font-weight:600;">{{$attr['app_title']}}</div>
							<div style="margin-top:1px;margin-left: -4px;"></div>
						</div>

					@else
						{{-- DDP not enabled --}}
						<div class="float-start" style="width:180px;"><span style="font-size:12px;color:#999;padding:10px;">{{$lang['data_entry_380']}}</span></div>
						
						<div class="float-start">
							<div class="ehr-project-title-{{$project_id}}" style="font-size:14px;font-weight:600;">{{$attr['app_title']}}</div>
							<div style="color:#A00000;font-size:12px;">{{$lang['data_entry_431']}}</div>
						</div>
					@endif
						<div class="float-end" style="">
							<button class="btn btn-danger btn-xs" onclick="connector.removeProject({{$project_id}})"
								>{{$lang['data_entry_369']}}</button>
						</div>
					</td>
				</tr>
			@endforeach
			
			{{-- If no projects have been registered, display instructions --}}
			@empty($registeredProjects)
				<tr class="odd">
					<td>
						<div style="font-weight:bold;color:#C00000;padding:10px 20px;">
							{{$lang['data_entry_386']}}
						</div>
						<div style="color:#A00000;padding:10px 20px 0;">
							{{$lang['data_entry_385']}} {{$lang['data_entry_430']}}
						</div>
					</td>
				</tr>
			@endempty

			</tbody>
		</table>
		<div style="margin: 20px 10px;">{{$lang['ws_257']}}<a href="javascript:;" style="text-decoration:underline;" onclick="connector.ddpExplainDialog();">{{$lang['ws_211']}}</a></div>
	</div>
</div>

@empty($mrnValidationTypes)
	{{-- If no MRN field validations are enabled, then display warning message --}}
	<div class="red">{{$lang['data_entry_388']}}</div>
@endempty

{{-- Dialog box --}}
<div id="addPatientDialog" class="simpleDialog" title="{{$lang['data_entry_402']}} {{$lang['data_entry_376']}}{{$lang['questionmark']}}">
	{{$lang['data_entry_382']}} <span id="newRecordNameAutoNumText">{{$lang['data_entry_383']}}</span>
	<div style="margin-top:10px;"><b>{{$lang['create_project_87']}}</b>&nbsp;&nbsp;<span id="newRecordNameProjTitle" style="color:#C00000;"></span></div>
	<div id="newRecordNameDiv" style="margin-top:10px;">
		<b>{{$lang['data_entry_381']}}</b>&nbsp;&nbsp;<input id="newRecordName" class="x-form-text x-form-field" type="text">
	</div>
</div>

{{-- CDP explanation dialog --}}
<div id="ddpExplainDialog" class="simpleDialog" title="{{ $lang['ws_210'] }}">
	{!! $ddpExplanationDialogContent !!}
</div>

@endsection