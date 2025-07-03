#!/usr/bin/env perl

use strict;
use warnings;
use LWP::Curl;
use JSON;

my %config = do 'config.pl';

my $record = {
	unique_role_name           => 'U-527D39JXAC',
	role_label                 => 'Project Manager',
	data_access_group          => 1,
	data_export_tool           => 1,
	mobile_app                 => 1,
	mobile_app_download_data   => 1,
	lock_records_all_forms     => 1,
	lock_records               => 1,
	lock_records_customization => 1,
	record_delete              => 1,
	record_rename              => 1,
	record_create              => 1,
	api_import                 => 1,
	api_export                 => 1,
	api_modules                => 1,
	data_quality_execute       => 1,
	data_quality_create        => 1,
	file_repository            => 1,
	logging                    => 1,
	data_comparison_tool       => 1,
	data_import_tool           => 1,
	calendar                   => 1,
	stats_and_charts           => 1,
	reports                    => 1,
	user_rights                => 1,
	design                     => 1,
};

my $json = JSON->new->allow_nonref;
my $data = $json->encode([$record]);

my $fields = {
    token   => $config{api_token},
    content => 'userRole',
    format  => 'json',
    data    => $data,
};

my $ch = LWP::Curl->new(auto_encode => 0);
my $content = $ch->post($config{api_url}, $fields, $config{referer});

print $content;
