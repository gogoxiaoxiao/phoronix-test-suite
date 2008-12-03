<?php

/*
	Phoronix Test Suite
	URLs: http://www.phoronix.com, http://www.phoronix-test-suite.com/
	Copyright (C) 2008, Phoronix Media
	Copyright (C) 2008, Michael Larabel

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 3 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

class run_test implements pts_option_interface
{
	public static function run($r)
	{
		require("pts-core/functions/pts-functions-run.php");
		require("pts-core/functions/pts-functions-merge.php");

		if(IS_BATCH_MODE && pts_read_user_config(P_OPTION_BATCH_CONFIGURED, "FALSE") == "FALSE")
		{
			echo pts_string_header("The batch mode must first be configured\nRun: phoronix-test-suite batch-setup");
			return false;
		}

		$TO_RUN = strtolower($r[0]);

		if(is_file($r[0]) && substr(basename($r[0]), -4) == ".svg")
		{
			$test_extracted = pts_prompt_svg_result_options($r[0]);

			if(!empty($test_extracted))
			{
				$TO_RUN = $test_extracted;
			}
		}

		$TO_RUN_TYPE = pts_test_type($TO_RUN);
		$MODULE_STORE = pts_module_store_var("TO_STRING");
		$TEST_PROPERTIES = array();

		if(IS_BATCH_MODE)
		{
			array_push($TEST_PROPERTIES, "PTS_BATCH_MODE");
		}
		if(IS_SCTP_MODE)
		{
			$TO_RUN = basename($TO_RUN);
		}

		if(empty($TO_RUN))
		{
			echo pts_string_header("The test, suite, or saved file name must be supplied.");
			return false;
		}
		pts_set_assignment("TO_RUN", $TO_RUN);

		if(pts_is_test($TO_RUN))
		{
			$xml_parser = new pts_test_tandem_XmlReader(pts_location_test($TO_RUN));
			$test_title = $xml_parser->getXMLValue(P_TEST_TITLE);

			if(empty($test_title))
			{
				echo pts_string_header($TO_RUN . " is not a test.");
				return false;
			}
		}

		// Make sure tests are installed
		$verify_result = pts_verify_test_installation($TO_RUN);

		if($verify_result == false)
		{
			return false;
		}

		if(!$TO_RUN_TYPE)
		{
			if(is_file(SAVE_RESULTS_DIR . $TO_RUN . "/composite.xml"))
			{
				$TO_RUN_TYPE = "LOCAL_COMPARISON";
			}
			else if(is_file(pts_input_correct_results_path($TO_RUN)))
			{
				$TO_RUN_TYPE = "LOCAL_COMPARISON";
			}
			else if(pts_is_global_id($TO_RUN))
			{
				$TO_RUN_TYPE = "GLOBAL_COMPARISON";
				pts_set_assignment("GLOBAL_COMPARISON", 1);
				pts_save_result($TO_RUN . "/composite.xml", pts_global_download_xml($TO_RUN));
			}
			else
			{
				echo pts_string_header("Not Recognized: " . $TO_RUN);
				return false;
			}

			$SAVE_RESULTS = true;
			$PROPOSED_FILE_NAME = $TO_RUN;
			$RES_NULL = null;
		}
		else
		{
			echo "\n";
			if(IS_SCTP_MODE)
			{
				$SAVE_RESULTS = true;
				$CUSTOM_TILE = $TO_RUN;
				$TO_RUN_TYPE = "SCTP_COMPARISON";
				$PROPOSED_FILE_NAME = $TO_RUN;
				$RES_NULL = null;
			}
			else if(getenv("PTS_SAVE_RESULTS") == "NO")
			{
				$SAVE_RESULTS = false;
			}
			else if(getenv("TEST_RESULTS_NAME") != false)
			{
				$SAVE_RESULTS = true;
			}
			else
			{
				$save_option = true;

				if(pts_is_test($TO_RUN))
				{
					$xml_parser = new pts_test_tandem_XmlReader(pts_location_test($TO_RUN));
					$result_format = $xml_parser->getXMLValue(P_TEST_RESULTFORMAT);

					if($result_format == "NO_RESULT")
					{
						$save_option = false;
					}
				}

				if($save_option)
				{
					$SAVE_RESULTS = pts_bool_question("Would you like to save these test results (Y/n)?", true, "SAVE_RESULTS");
				}
				else
				{
					$SAVE_RESULTS = false;
				}
			}

			if($SAVE_RESULTS)
			{
				$FILE_NAME = pts_prompt_save_file_name();
				$PROPOSED_FILE_NAME = $FILE_NAME[0];
				$CUSTOM_TITLE = $FILE_NAME[1];

				$validate_r = pts_validate_save_results_name($PROPOSED_FILE_NAME, $TO_RUN, $TO_RUN_TYPE);

				if($PROPOSED_FILE_NAME != $validate_r[0] && !empty($validate_r[1]))
				{
					$PROPOSED_FILE_NAME = $validate_r[0];
					$CUSTOM_TITLE = $validate_r[1];
				}
			}
		}

		$RESULTS = new tandem_XmlWriter();
		$RESULTS_IDENTIFIER = "";

		if($SAVE_RESULTS)
		{
			if(is_file(SAVE_RESULTS_DIR . $PROPOSED_FILE_NAME . "/composite.xml"))
			{
				$xml_parser = new tandem_XmlReader(SAVE_RESULTS_DIR . $PROPOSED_FILE_NAME . "/composite.xml");
				$raw_results = $xml_parser->getXMLArrayValues(P_RESULTS_RESULTS_GROUP);
				$result_identifiers = array();

				for($i = 0; $i < count($raw_results); $i++)
				{
					$results_xml = new tandem_XmlReader($raw_results[$i]);
					array_push($result_identifiers, $results_xml->getXMLArrayValues(S_RESULTS_RESULTS_GROUP_IDENTIFIER));
				}
			}
			else
			{
				$result_identifiers = array();
			}

			$RESULTS_IDENTIFIER = pts_prompt_results_identifier($result_identifiers);
			pts_set_assignment("SAVE_FILE_NAME", $PROPOSED_FILE_NAME);
		}
		else
		{
			pts_set_assignment("SAVE_FILE_NAME", null);
		}

		if(pts_is_test($TO_RUN))
		{
			if(!IS_BATCH_MODE)
			{
				$option_output = pts_prompt_test_options($TO_RUN);

				$TEST_RUN = array($TO_RUN);
				$TEST_ARGS = array($option_output[0]);
				$TEST_ARGS_DESCRIPTION = array($option_output[1]);
			}
			else
			{
				$option_output = pts_generate_batch_run_options($TO_RUN);

				$TEST_RUN = array();
				$TEST_ARGS = $option_output[0];
				$TEST_ARGS_DESCRIPTION = $option_output[1];
				for($i = 0; $i < count($TEST_ARGS); $i++) // needed at this time to fill up the array same size as the number of options present
				{
					array_push($TEST_RUN, $TO_RUN);
				}
			}

			if($SAVE_RESULTS)
			{
				$xml_parser = new pts_test_tandem_XmlReader(pts_location_test($TO_RUN));
				$test_description = $xml_parser->getXMLValue(P_TEST_DESCRIPTION);
				$test_version = $xml_parser->getXMLValue(P_TEST_PTSVERSION);
				$test_type = $xml_parser->getXMLValue(P_TEST_HARDWARE_TYPE);
				unset($xml_parser);
			}
		}
		else if(pts_is_suite($TO_RUN))
		{
			echo pts_string_header("Test Suite: " . $TO_RUN);

			$xml_parser = new tandem_XmlReader(pts_location_suite($TO_RUN));

			if($SAVE_RESULTS)
			{
				$test_description = $xml_parser->getXMLValue(P_SUITE_DESCRIPTION);
				$test_version = $xml_parser->getXMLValue(P_SUITE_VERSION);
				$test_type = $xml_parser->getXMLValue(P_SUITE_TYPE);
			}

			$TEST_RUN = $xml_parser->getXMLArrayValues(P_SUITE_TEST_NAME);
			$TEST_ARGS = $xml_parser->getXMLArrayValues(P_SUITE_TEST_ARGUMENTS);
			$TEST_ARGS_DESCRIPTION = $xml_parser->getXMLArrayValues(P_SUITE_TEST_DESCRIPTION);

			$SUITE_PRE_RUN_MESSAGE = $xml_parser->getXMLValue(P_SUITE_PRERUNMSG);
			$SUITE_POST_RUN_MESSAGE = $xml_parser->getXMLValue(P_SUITE_POSTRUNMSG);
			$SUITE_RUN_MODE = $xml_parser->getXMLValue(P_SUITE_RUNMODE);

			if($SUITE_RUN_MODE == "PCQS")
			{
				pts_set_assignment("IS_PCQS_MODE", true);
			}

			unset($xml_parser);
		}
		else if($SAVE_RESULTS && ($TO_RUN_TYPE == "GLOBAL_COMPARISON" || $TO_RUN_TYPE == "LOCAL_COMPARISON"))
		{
			echo pts_string_header("PTS Comparison: " . $TO_RUN);

			$xml_parser = new tandem_XmlReader(SAVE_RESULTS_DIR . $TO_RUN . "/composite.xml");
			$CUSTOM_TITLE = $xml_parser->getXMLValue(P_RESULTS_SUITE_TITLE);
			$test_description = $xml_parser->getXMLValue(P_RESULTS_SUITE_DESCRIPTION);
			$test_extensions = $xml_parser->getXMLValue(P_RESULTS_SUITE_EXTENSIONS);
			$test_previous_properties = $xml_parser->getXMLValue(P_RESULTS_SUITE_PROPERTIES);
			$test_version = $xml_parser->getXMLValue(P_RESULTS_SUITE_VERSION);
			$test_type = $xml_parser->getXMLValue(P_RESULTS_SUITE_TYPE);
			$TEST_RUN = $xml_parser->getXMLArrayValues(P_RESULTS_TEST_TESTNAME);
			$TEST_ARGS = $xml_parser->getXMLArrayValues(P_RESULTS_TEST_ARGUMENTS);
			$TEST_ARGS_DESCRIPTION = $xml_parser->getXMLArrayValues(P_RESULTS_TEST_ATTRIBUTES);
			unset($xml_parser);

			foreach(explode(";", $test_previous_properties) as $test_prop)
			{
				if(!in_array($test_prop, $TEST_PROPERTIES))
				{
					array_push($TEST_PROPERTIES, $test_prop);
				}
			}

			pts_module_process_extensions($test_extensions, $MODULE_STORE);
		}
		else
		{
			echo pts_string_header("\nUnrecognized option: " . $TO_RUN_TYPE . "\n");
			return false;
		}

		if($SAVE_RESULTS && (!IS_BATCH_MODE || pts_read_user_config(P_OPTION_BATCH_PROMPTDESCRIPTION, "FALSE") == "TRUE"))
		{
			if(empty($test_description))
			{
				$test_description = "N/A";
			}

			echo pts_string_header("If you wish, enter a new description below.\nPress ENTER to proceed without changes.", "#");
			echo "Current Description: " . $test_description;
			echo "\n\nNew Description: ";
			$new_test_description = trim(fgets(STDIN));

			if(!empty($new_test_description))
			{
				$test_description = $new_test_description;
			}
		}

		if(pts_is_suite($TO_RUN))
		{
			pts_user_message($SUITE_PRE_RUN_MESSAGE);
		}

		// Run the test process

		pts_recurse_call_tests($TEST_RUN, $TEST_ARGS, $SAVE_RESULTS, $RESULTS, $RESULTS_IDENTIFIER, $TEST_ARGS_DESCRIPTION);

		if(pts_is_suite($TO_RUN))
		{
			pts_user_message($SUITE_POST_RUN_MESSAGE);
		}

		pts_set_assignment("PTS_TESTING_DONE", 1);
		pts_module_process("__post_run_process", $TEST_RUN);

		if($SAVE_RESULTS)
		{
			$test_notes = pts_generate_test_notes($test_type);

			$id = pts_request_new_id();
			$RESULTS->setXslBinding("pts-results-viewer.xsl");
			$RESULTS->addXmlObject(P_RESULTS_SYSTEM_HARDWARE, $id, pts_hw_string());
			$RESULTS->addXmlObject(P_RESULTS_SYSTEM_SOFTWARE, $id, pts_sw_string());
			$RESULTS->addXmlObject(P_RESULTS_SYSTEM_AUTHOR, $id, pts_current_user());
			$RESULTS->addXmlObject(P_RESULTS_SYSTEM_DATE, $id, date("F j, Y h:i A"));
			$RESULTS->addXmlObject(P_RESULTS_SYSTEM_NOTES, $id, trim($test_notes));
			$RESULTS->addXmlObject(P_RESULTS_SYSTEM_PTSVERSION, $id, PTS_VERSION);
			$RESULTS->addXmlObject(P_RESULTS_SYSTEM_IDENTIFIERS, $id, $RESULTS_IDENTIFIER);

			$id = pts_request_new_id();
			$RESULTS->addXmlObject(P_RESULTS_SUITE_TITLE, $id, $CUSTOM_TITLE);
			$RESULTS->addXmlObject(P_RESULTS_SUITE_NAME, $id, $TO_RUN);
			$RESULTS->addXmlObject(P_RESULTS_SUITE_VERSION, $id, $test_version);
			$RESULTS->addXmlObject(P_RESULTS_SUITE_DESCRIPTION, $id, $test_description);
			$RESULTS->addXmlObject(P_RESULTS_SUITE_TYPE, $id, $test_type);
			$RESULTS->addXmlObject(P_RESULTS_SUITE_EXTENSIONS, $id, $MODULE_STORE);
			$RESULTS->addXmlObject(P_RESULTS_SUITE_PROPERTIES, $id, implode(";", $TEST_PROPERTIES));

			if(pts_read_assignment("TEST_RAN") == true)
			{
				pts_save_test_file($PROPOSED_FILE_NAME, $RESULTS);
				echo "Results Saved To: " . SAVE_RESULTS_DIR . $PROPOSED_FILE_NAME . "/composite.xml\n";
				pts_display_web_browser(SAVE_RESULTS_DIR . $PROPOSED_FILE_NAME . "/index.html");

				$upload_results = pts_bool_question("Would you like to upload these results to Phoronix Global (Y/n)?", true, "UPLOAD_RESULTS");

				if($upload_results)
				{
					$tags_input = pts_promt_user_tags(array($RESULTS_IDENTIFIER));
					$upload_url = pts_global_upload_result(SAVE_RESULTS_DIR . $PROPOSED_FILE_NAME . "/composite.xml", $tags_input);

					if(!empty($upload_url))
					{
						echo "\nResults Uploaded To: " . $upload_url . "\n";
						pts_module_process("__event_global_upload", $upload_url);
						pts_display_web_browser("\"" . $upload_url . "\"", "Do you want to launch Phoronix Global", true);
					}
					else
					{
						echo "\nResults Failed To Upload.\n";
					}
				}
				echo "\n";
			}
		}
	}
}

?>
