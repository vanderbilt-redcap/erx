<?php
/**
 * PLUGIN NAME: ERX
 * DESCRIPTION: Automatically add/update records in the Adherence Intervention Study project
 * VERSION: 1.0
 * AUTHOR: carl.w.reed@vumc.org
 */

// Call the REDCap Connect file in the main "redcap" directory
require_once "../../redcap_connect.php";
require_once "base.php";

# project variables
define("PID", 14);
$project = new \Project(PID);
$eid = $project->firstEventId;

# import variables
$importDataFilename = "pdc_redcap_import.csv";
$imported = file($importDataFilename);

$data = [];
$ignored = [];

echo "<pre>\n";
// # process imported data so that $baselineData and $pdcData will be ready to save

$rows = ((count($imported) + 1) / 2);
for ($i = 1; $i < $rows; $i++) {
	# convert csv to array
	$line1 = str_getcsv($imported[$i*2-1]);
	$line2 = str_getcsv($imported[$i*2]);
	# convert dates
	$line1[3] = (new DateTime($line1[3]))->format("Y-m-d");
	$line1[6] = (new DateTime($line1[6]))->format("Y-m-d");
	$line2[18] = (new DateTime($line2[18]))->format("Y-m-d");
	$line2[19] = (new DateTime($line2[19]))->format("Y-m-d");
	
	# get record_id for this pair of imported rows
	$rid = $line1[0];
	
	# set last_fill_date for all records to today
	$newLastFillDate = (new DateTime())->format("Y-m-d");
	
	# see if already randomized
	$recordData = \REDCap::getData(PID, 'array', $rid);
	$saveNeeded = true;
	if (!empty($recordData)) {
		# if already randomized, or newer data present, don't save
		# see if randomized by checking baseline.confirm and baseline.randomization_complete
		if ($recordData[$rid][$eid]["confirm"] == 1 && $recordData[$rid][$eid]["randomization_complete"] == 2) {
			$saveNeeded = false;
			$ignored[$rid] = "reason for ignore -- randomized already / confirm = 1 and randomization_complete = 2";
		}
		# if last_fill_date > import_data, don't save
		$existingFillDate = $recordData[$rid]["repeat_instances"][$eid][$line2[1]][$line2[2]]["last_fill_date"];
		if ($existingFillDate >= $newLastFillDate) {
			// echo "\nin";
			$saveNeeded = false;
			$ignored[$rid] = "reason: existing last_fill_date (" . $existingFillDate . ") is >= import_date (" . $line1[3].")";
		} else {
			echo "existing: ".$existingFillDate." -- current: $newLastFillDate\n";
		}
	}
	
	if ($saveNeeded) {
		$data[$rid] = [
			$eid => [
				"import_date" => $line1[3],
				"mrn_gpi" => $line1[4],
				"sex" => $line1[5],
				// "ethnicity" => $line1[asoidufhaisduf],
				// "race" => $line1[asoidufhaisduf],
				"date_birth" => $line1[6],
				// "age" => $line1[7],
				"insurance" => $line1[8],
				"oop" => $line1[9],
				"zip" => $line1[10],
				"med_name" => $line1[11],
				"clinic" => $line1[12],
				"clinic_level" => $line1[13]
				// "vsp_pat" => $line1[14]
			],
			"repeat_instances" => [
				$eid => [
					# "pdc_measurement"
					$line2[1] => [
						# 1 or int
						$line2[2] => [
							// "last_fill_date" => $line2[18],
							"last_fill_date" => $newLastFillDate,
							"measure_date" => $line2[19],
							"gap_days" => $line2[20],
							"pdc_measurement_4mths" => $line2[21],
							"pdc_measurement_12mths" => $line2[22],
						]
					]
				]
			]
		];
	}
}

$results = \REDCap::saveData(
	$project_id = PID,
	$dataFormat = 'array',
	$data,
	$overwriteBehavior = 'normal',
	$dateFormat = 'YMD',
	$type = 'flat',
	$dataAccessGroup = NULL,
	$dataLogging = TRUE,
	$performAutoCalc = TRUE,
	$commitData = TRUE
);

// print_r($results);

echo "Total records ignored: " . count($ignored) . "\n";
print_r($ignored);

echo "\n\nTotal records added/updated: " . count($results['ids']) . "\n";
print_r($results['ids']);

echo "</pre>";