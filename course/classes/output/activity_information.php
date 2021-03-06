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

/**
 * File containing the class activity information renderable.
 *
 * @package    core_course
 * @copyright  2021 Jun Pataleta <jun@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace core_course\output;

defined('MOODLE_INTERNAL') || die();

use cm_info;
use completion_info;
use context;
use core\activity_dates;
use core_completion\cm_completion_details;
use core_user;
use core_user\fields;
use renderable;
use renderer_base;
use stdClass;
use templatable;

/**
 * The activity information renderable class.
 *
 * @package    core_course
 * @copyright  2021 Jun Pataleta <jun@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class activity_information implements renderable, templatable {

    /** @var cm_info The course module information. */
    protected $cminfo = null;

    /** @var array The array of relevant dates for this activity. */
    protected $activitydates = [];

    /** @var cm_completion_details The user's completion details for this activity. */
    protected $cmcompletion = null;

    /**
     * Constructor.
     *
     * @param cm_info $cminfo The course module information.
     * @param cm_completion_details $cmcompletion The course module information.
     * @param array $activitydates The activity dates.
     */
    public function __construct(cm_info $cminfo, cm_completion_details $cmcompletion, array $activitydates) {
        $this->cminfo = $cminfo;
        $this->cmcompletion = $cmcompletion;
        $this->activitydates = $activitydates;
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param renderer_base $output Renderer base.
     * @return stdClass
     */
    public function export_for_template(renderer_base $output): stdClass {
        $data = $this->build_completion_data();

        $data->cmid = $this->cminfo->id;
        $data->activityname = $this->cminfo->name;
        $data->activitydates = $this->activitydates;

        return $data;
    }

    /**
     * Builds the completion data for export.
     *
     * @return stdClass
     */
    protected function build_completion_data(): stdClass {
        $data = new stdClass();

        $data->hascompletion = $this->cmcompletion->has_completion();
        $data->isautomatic = $this->cmcompletion->is_automatic();

        // Get the name of the user overriding the completion condition, if available.
        $data->overrideby = null;
        $overrideby = $this->cmcompletion->overridden_by();
        $overridebyname = null;
        if (!empty($overrideby)) {
            $userfields = fields::for_name();
            $overridebyrecord = core_user::get_user($overrideby, 'id ' . $userfields->get_sql()->selects, MUST_EXIST);
            $data->overrideby = fullname($overridebyrecord);
        }

        // We'll show only the completion conditions and not the completion status if we're not tracking completion for this user
        // (e.g. a teacher, admin).
        $data->istrackeduser = $this->cmcompletion->is_tracked_user();

        // Overall completion states.
        $overallcompletion = $this->cmcompletion->get_overall_completion();
        $data->overallcomplete = $overallcompletion == COMPLETION_COMPLETE;
        $data->overallincomplete = $overallcompletion == COMPLETION_INCOMPLETE;

        // Set an accessible description for manual completions with overridden completion state.
        if (!$data->isautomatic && $data->overrideby) {
            $setbydata = (object)[
                'activityname' => $this->cminfo->name,
                'setby' => $data->overrideby,
            ];
            $setbylangkey = $data->overallcomplete ? 'completion_setby:manual:done' : 'completion_setby:manual:markdone';
            $data->accessibledescription = get_string($setbylangkey, 'course', $setbydata);
        }

        // Build automatic completion details.
        $details = [];
        foreach ($this->cmcompletion->get_details() as $key => $detail) {
            // Set additional attributes for the template.
            $detail->key = $key;
            $detail->statuscomplete = in_array($detail->status, [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS]);
            $detail->statuscompletefail = $detail->status == COMPLETION_COMPLETE_FAIL;
            $detail->statusincomplete = $detail->status == COMPLETION_INCOMPLETE;

            // Add an accessible description to be used for title and aria-label attributes for overridden completion details.
            if ($data->overrideby) {
                $setbydata = (object)[
                    'condition' => $detail->description,
                    'setby' => $data->overrideby,
                ];
                $detail->accessibledescription = get_string('completion_setby:auto', 'course', $setbydata);
            }

            // We don't need the status in the template.
            unset($detail->status);

            $details[] = $detail;
        }
        $data->completiondetails = $details;

        return $data;
    }
}
