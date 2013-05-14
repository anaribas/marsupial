<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

require_once('../../config.php');
require_once($CFG->dirroot.'/mod/rscorm/locallib.php');

$id = optional_param('id', '', PARAM_INT);       // Course Module ID, or
$a = optional_param('a', '', PARAM_INT);         // scorm ID
$scoid = required_param('scoid', PARAM_INT);  // sco ID
$attempt = required_param('attempt', PARAM_INT);  // attempt number

if (!empty($id)) {
    if (! $cm = get_coursemodule_from_id('rscorm', $id)) {
        print_error('invalidcoursemodule');
    }
    if (! $course = $DB->get_record("course", array("id"=>$cm->course))) {
        print_error('coursemisconf');
    }
    if (! $scorm = $DB->get_record("rscorm", array("id"=>$cm->instance))) {
        print_error('invalidcoursemodule');
    }
} else if (!empty($a)) {
    if (! $scorm = $DB->get_record("rscorm", array("id"=>$a))) {
        print_error('invalidcoursemodule');
    }
    if (! $course = $DB->get_record("course", array("id"=>$scorm->course))) {
        print_error('coursemisconf');
    }
    if (! $cm = get_coursemodule_from_instance("rscorm", $scorm->id, $course->id)) {
        print_error('invalidcoursemodule');
    }
} else {
    print_error('missingparameter');
}

$PAGE->set_url('/mod/rscorm/datamodel.php', array('scoid'=>$scoid, 'attempt'=>$attempt, 'id'=>$cm->id));

//MARSUPIAL ********** MODIFICA - allow context course
// 2012.12.17 @abertranb
require_login($course);
// ********* ORIGINAL
//require_login($course, false, $cm);
// ********* FI
if (confirm_sesskey() && (!empty($scoid))) {
    $result = true;
    $request = null;
    if (has_capability('mod/rscorm:savetrack', get_context_instance(CONTEXT_MODULE, $cm->id))) {
        foreach (data_submitted() as $element => $value) {
            $element = str_replace('__', '.', $element);
            if (substr($element, 0, 3) == 'cmi') {
                $netelement = preg_replace('/\.N(\d+)\./', "\.\$1\.", $element);
                $result = rscorm_insert_track($USER->id, $scorm->id, $scoid, $attempt, $element, $value, $scorm->forcecompleted) && $result;
            }
            if (substr($element, 0, 15) == 'adl.nav.request') {
                // RSCORM 2004 Sequencing Request
                require_once($CFG->dirroot.'/mod/rscorm/datamodels/sequencinglib.php');

                $search = array('@continue@', '@previous@', '@\{target=(\S+)\}choice@', '@exit@', '@exitAll@', '@abandon@', '@abandonAll@');
                $replace = array('continue_', 'previous_', '\1', 'exit_', 'exitall_', 'abandon_', 'abandonall');
                $action = preg_replace($search, $replace, $value);

                if ($action != $value) {
                    // Evaluating navigation request
                    $valid = rscorm_seq_overall ($scoid, $USER->id, $action, $attempt);
                    $valid = 'true';

                    // Set valid request
                    $search = array('@continue@', '@previous@', '@\{target=(\S+)\}choice@');
                    $replace = array('true', 'true', 'true');
                    $matched = preg_replace($search, $replace, $value);
                    if ($matched == 'true') {
                        $request = 'adl.nav.request_valid["'.$action.'"] = "'.$valid.'";';
                    }
                }
            }
            // Log every datamodel update requested
            if (substr($element, 0, 15) == 'adl.nav.request' || substr($element, 0, 3) == 'cmi') {
                if (rscorm_debugging($scorm)) {
                    add_to_log($course->id, 'rscorm', 'trk: scoid/'.$scoid.' at: '.$attempt, 'view.php?id='.$cm->id, "$element => $value", $cm->id);
                }
            }
        }
    }
    if ($result) {
        echo "true\n0";
    } else {
        echo "false\n101";
    }
    if ($request != null) {
        echo "\n".$request;
    }
}
