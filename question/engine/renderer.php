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
 * Renderers for outputting parts of the question engine.
 *
 * @package    moodlecore
 * @subpackage questionengine
 * @copyright  2009 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core_question\output\question_version_info;

defined('MOODLE_INTERNAL') || die();


/**
 * This renderer controls the overall output of questions. It works with a
 * {@link qbehaviour_renderer} and a {@link qtype_renderer} to output the
 * type-specific bits. The main entry point is the {@link question()} method.
 *
 * @copyright  2009 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class core_question_renderer extends plugin_renderer_base {

    /**
     * Generate the display of a question in a particular state, and with certain
     * display options. Normally you do not call this method directly. Intsead
     * you call {@link question_usage_by_activity::render_question()} which will
     * call this method with appropriate arguments.
     *
     * @param question_attempt $qa the question attempt to display.
     * @param qbehaviour_renderer $behaviouroutput the renderer to output the behaviour
     *      specific parts.
     * @param qtype_renderer $qtoutput the renderer to output the question type
     *      specific parts.
     * @param question_display_options $options controls what should and should not be displayed.
     * @param string|null $number The question number to display. 'i' is a special
     *      value that gets displayed as Information. Null means no number is displayed.
     * @return string HTML representation of the question.
     */
    public function question(question_attempt $qa, qbehaviour_renderer $behaviouroutput,
            qtype_renderer $qtoutput, question_display_options $options, $number) {

        // If not already set, record the questionidentifier.
        $options = clone($options);
        if (!$options->has_question_identifier()) {
            $options->questionidentifier = $this->question_number_text($number);
        }

        $output = '';
        $output .= html_writer::start_tag('div', array(
            'id' => $qa->get_outer_question_div_unique_id(),
            'class' => implode(' ', array(
                'que',
                $qa->get_question(false)->get_type_name(),
                $qa->get_behaviour_name(),
                $qa->get_state_class($options->correctness && $qa->has_marks()),
            ))
        ));

        $output .= html_writer::tag('div',
                $this->info($qa, $behaviouroutput, $qtoutput, $options, $number),
                array('class' => 'info'));

        $output .= html_writer::start_tag('div', array('class' => 'content'));

        $output .= html_writer::tag('div',
                $this->add_part_heading($qtoutput->formulation_heading(),
                    $this->formulation($qa, $behaviouroutput, $qtoutput, $options)),
                array('class' => 'formulation clearfix'));
        $output .= html_writer::nonempty_tag('div',
                $this->add_part_heading(get_string('feedback', 'question'),
                    $this->outcome($qa, $behaviouroutput, $qtoutput, $options)),
                array('class' => 'outcome clearfix'));
        $output .= html_writer::nonempty_tag('div',
                $this->add_part_heading(get_string('comments', 'question'),
                    $this->manual_comment($qa, $behaviouroutput, $qtoutput, $options)),
                array('class' => 'comment clearfix'));
        $output .= html_writer::nonempty_tag('div',
                $this->response_history($qa, $behaviouroutput, $qtoutput, $options),
                array('class' => 'history clearfix border p-2'));

        $output .= html_writer::end_tag('div');
        $output .= html_writer::end_tag('div');
        return $output;
    }

    /**
     * Generate the information bit of the question display that contains the
     * metadata like the question number, current state, and mark.
     * @param question_attempt $qa the question attempt to display.
     * @param qbehaviour_renderer $behaviouroutput the renderer to output the behaviour
     *      specific parts.
     * @param qtype_renderer $qtoutput the renderer to output the question type
     *      specific parts.
     * @param question_display_options $options controls what should and should not be displayed.
     * @param string|null $number The question number to display. 'i' is a special
     *      value that gets displayed as Information. Null means no number is displayed.
     * @return HTML fragment.
     */
    protected function info(question_attempt $qa, qbehaviour_renderer $behaviouroutput,
            qtype_renderer $qtoutput, question_display_options $options, $number) {
        $output = '';
        $output .= $this->number($number);
        $output .= $this->status($qa, $behaviouroutput, $options);
        $output .= $this->mark_summary($qa, $behaviouroutput, $options);
        $output .= $this->question_flag($qa, $options->flags);
        $output .= $this->edit_question_link($qa, $options);
        if ($options->versioninfo) {
            $output .= $this->render(new question_version_info($qa->get_question(), true));
        }
        return $output;
    }

    /**
     * Generate the display of the question number.
     * @param string|null $number The question number to display. 'i' is a special
     *      value that gets displayed as Information. Null means no number is displayed.
     * @return HTML fragment.
     */
    protected function number($number) {
        if (trim($number ?? '') === '') {
            return '';
        }
        if (trim($number) === 'i') {
            $numbertext = get_string('information', 'question');
        } else {
            $numbertext = get_string('questionx', 'question',
                    html_writer::tag('span', s($number), array('class' => 'qno')));
        }
        return html_writer::tag('h3', $numbertext, array('class' => 'no'));
    }

    /**
     * Get the question number as a string.
     *
     * @param string|null $number e.g. '123' or 'i'. null or '' means do not display anything number-related.
     * @return string e.g. 'Question 123' or 'Information' or ''.
     */
    protected function question_number_text(?string $number): string {
        $number = $number ?? '';
        // Trim the question number of whitespace, including &nbsp;.
        $trimmed = trim(html_entity_decode($number), " \n\r\t\v\x00\xC2\xA0");
        if ($trimmed === '') {
            return '';
        }
        if (trim($number) === 'i') {
            return get_string('information', 'question');
        } else {
            return get_string('questionx', 'question', s($number));
        }
    }

    /**
     * Add an invisible heading like 'question text', 'feebdack' at the top of
     * a section's contents, but only if the section has some content.
     * @param string $heading the heading to add.
     * @param string $content the content of the section.
     * @return string HTML fragment with the heading added.
     */
    protected function add_part_heading($heading, $content) {
        if ($content) {
            $content = html_writer::tag('h4', $heading, array('class' => 'accesshide')) . $content;
        }
        return $content;
    }

    /**
     * Generate the display of the status line that gives the current state of
     * the question.
     * @param question_attempt $qa the question attempt to display.
     * @param qbehaviour_renderer $behaviouroutput the renderer to output the behaviour
     *      specific parts.
     * @param question_display_options $options controls what should and should not be displayed.
     * @return HTML fragment.
     */
    protected function status(question_attempt $qa, qbehaviour_renderer $behaviouroutput,
            question_display_options $options) {
        return html_writer::tag('div', $qa->get_state_string($options->correctness),
                array('class' => 'state'));
    }

    /**
     * Generate the display of the marks for this question.
     * @param question_attempt $qa the question attempt to display.
     * @param qbehaviour_renderer $behaviouroutput the behaviour renderer, which can generate a custom display.
     * @param question_display_options $options controls what should and should not be displayed.
     * @return HTML fragment.
     */
    protected function mark_summary(question_attempt $qa, qbehaviour_renderer $behaviouroutput, question_display_options $options) {
        return html_writer::nonempty_tag('div',
                $behaviouroutput->mark_summary($qa, $this, $options),
                array('class' => 'grade'));
    }

    /**
     * Generate the display of the marks for this question.
     * @param question_attempt $qa the question attempt to display.
     * @param question_display_options $options controls what should and should not be displayed.
     * @return HTML fragment.
     */
    public function standard_mark_summary(question_attempt $qa, qbehaviour_renderer $behaviouroutput, question_display_options $options) {
        if (!$options->marks) {
            return '';

        } else if ($qa->get_max_mark() == 0) {
            return get_string('notgraded', 'question');

        } else if ($options->marks == question_display_options::MAX_ONLY ||
                is_null($qa->get_fraction())) {
            return $behaviouroutput->marked_out_of_max($qa, $this, $options);

        } else {
            return $behaviouroutput->mark_out_of_max($qa, $this, $options);
        }
    }

    /**
     * Generate the display of the available marks for this question.
     * @param question_attempt $qa the question attempt to display.
     * @param question_display_options $options controls what should and should not be displayed.
     * @return HTML fragment.
     */
    public function standard_marked_out_of_max(question_attempt $qa, question_display_options $options) {
        return get_string('markedoutofmax', 'question', $qa->format_max_mark($options->markdp));
    }

    /**
     * Generate the display of the marks for this question out of the available marks.
     * @param question_attempt $qa the question attempt to display.
     * @param question_display_options $options controls what should and should not be displayed.
     * @return HTML fragment.
     */
    public function standard_mark_out_of_max(question_attempt $qa, question_display_options $options) {
        $a = new stdClass();
        $a->mark = $qa->format_mark($options->markdp);
        $a->max = $qa->format_max_mark($options->markdp);
        return get_string('markoutofmax', 'question', $a);
    }

    /**
     * Render the question flag, assuming $flagsoption allows it.
     *
     * @param question_attempt $qa the question attempt to display.
     * @param int $flagsoption the option that says whether flags should be displayed.
     */
    protected function question_flag(question_attempt $qa, $flagsoption) {
        $divattributes = array('class' => 'questionflag');

        switch ($flagsoption) {
            case question_display_options::VISIBLE:
                $flagcontent = $this->get_flag_html($qa->is_flagged());
                break;

            case question_display_options::EDITABLE:
                $id = $qa->get_flag_field_name();
                // The checkbox id must be different from any element name, because
                // of a stupid IE bug:
                // http://www.456bereastreet.com/archive/200802/beware_of_id_and_name_attribute_mixups_when_using_getelementbyid_in_internet_explorer/
                $checkboxattributes = array(
                    'type' => 'checkbox',
                    'id' => $id . 'checkbox',
                    'name' => $id,
                    'value' => 1,
                );
                if ($qa->is_flagged()) {
                    $checkboxattributes['checked'] = 'checked';
                }
                $postdata = question_flags::get_postdata($qa);

                $flagcontent = html_writer::empty_tag('input',
                                array('type' => 'hidden', 'name' => $id, 'value' => 0)) .
                        html_writer::empty_tag('input',
                                array('type' => 'hidden', 'value' => $postdata, 'class' => 'questionflagpostdata')) .
                        html_writer::empty_tag('input', $checkboxattributes) .
                        html_writer::tag('label', $this->get_flag_html($qa->is_flagged(), $id . 'img'),
                                array('id' => $id . 'label', 'for' => $id . 'checkbox')) . "\n";

                $divattributes = array(
                    'class' => 'questionflag editable',
                );

                break;

            default:
                $flagcontent = '';
        }

        return html_writer::nonempty_tag('div', $flagcontent, $divattributes);
    }

    /**
     * Work out the actual img tag needed for the flag
     *
     * @param bool $flagged whether the question is currently flagged.
     * @param string $id an id to be added as an attribute to the img (optional).
     * @return string the img tag.
     */
    protected function get_flag_html($flagged, $id = '') {
        if ($flagged) {
            $icon = 'i/flagged';
            $label = get_string('clickunflag', 'question');
        } else {
            $icon = 'i/unflagged';
            $label = get_string('clickflag', 'question');
        }
        $attributes = [
            'src' => $this->image_url($icon),
            'alt' => '',
            'class' => 'questionflagimage',
        ];
        if ($id) {
            $attributes['id'] = $id;
        }
        $img = html_writer::empty_tag('img', $attributes);
        $img .= html_writer::span($label);

        return $img;
    }

    /**
     * Generate the display of the edit question link.
     *
     * @param question_attempt $qa The question attempt to display.
     * @param question_display_options $options controls what should and should not be displayed.
     * @return string
     */
    protected function edit_question_link(question_attempt $qa, question_display_options $options) {
        if (empty($options->editquestionparams)) {
            return '';
        }

        $params = $options->editquestionparams;
        if ($params['returnurl'] instanceof moodle_url) {
            $params['returnurl'] = $params['returnurl']->out_as_local_url(false);
        }
        $params['id'] = $qa->get_question_id();
        $editurl = new moodle_url('/question/bank/editquestion/question.php', $params);

        return html_writer::tag('div', html_writer::link(
                $editurl, $this->pix_icon('t/edit', get_string('edit'), '', array('class' => 'iconsmall')) .
                get_string('editquestion', 'question')),
                array('class' => 'editquestion'));
    }

    /**
     * Generate the display of the formulation part of the question. This is the
     * area that contains the quetsion text, and the controls for students to
     * input their answers. Some question types also embed feedback, for
     * example ticks and crosses, in this area.
     *
     * @param question_attempt $qa the question attempt to display.
     * @param qbehaviour_renderer $behaviouroutput the renderer to output the behaviour
     *      specific parts.
     * @param qtype_renderer $qtoutput the renderer to output the question type
     *      specific parts.
     * @param question_display_options $options controls what should and should not be displayed.
     * @return HTML fragment.
     */
    protected function formulation(question_attempt $qa, qbehaviour_renderer $behaviouroutput,
            qtype_renderer $qtoutput, question_display_options $options) {
        $output = '';
        $output .= html_writer::empty_tag('input', array(
                'type' => 'hidden',
                'name' => $qa->get_control_field_name('sequencecheck'),
                'value' => $qa->get_sequence_check_count()));
        $output .= $qtoutput->formulation_and_controls($qa, $options);
        if ($options->clearwrong) {
            $output .= $qtoutput->clear_wrong($qa);
        }
        $output .= html_writer::nonempty_tag('div',
                $behaviouroutput->controls($qa, $options), array('class' => 'im-controls'));
        return $output;
    }

    /**
     * Generate the display of the outcome part of the question. This is the
     * area that contains the various forms of feedback.
     *
     * @param question_attempt $qa the question attempt to display.
     * @param qbehaviour_renderer $behaviouroutput the renderer to output the behaviour
     *      specific parts.
     * @param qtype_renderer $qtoutput the renderer to output the question type
     *      specific parts.
     * @param question_display_options $options controls what should and should not be displayed.
     * @return HTML fragment.
     */
    protected function outcome(question_attempt $qa, qbehaviour_renderer $behaviouroutput,
            qtype_renderer $qtoutput, question_display_options $options) {
        $output = '';
        $output .= html_writer::nonempty_tag('div',
                $qtoutput->feedback($qa, $options), array('class' => 'feedback'));
        $output .= html_writer::nonempty_tag('div',
                $behaviouroutput->feedback($qa, $options), array('class' => 'im-feedback'));
        $output .= html_writer::nonempty_tag('div',
                $options->extrainfocontent, array('class' => 'extra-feedback'));
        return $output;
    }

    protected function manual_comment(question_attempt $qa, qbehaviour_renderer $behaviouroutput,
            qtype_renderer $qtoutput, question_display_options $options) {
        return $qtoutput->manual_comment($qa, $options) .
                $behaviouroutput->manual_comment($qa, $options);
    }

    /**
     * Generate the display of the response history part of the question. This
     * is the table showing all the steps the question has been through.
     *
     * @param question_attempt $qa the question attempt to display.
     * @param qbehaviour_renderer $behaviouroutput the renderer to output the behaviour
     *      specific parts.
     * @param qtype_renderer $qtoutput the renderer to output the question type
     *      specific parts.
     * @param question_display_options $options controls what should and should not be displayed.
     * @return HTML fragment.
     */
    protected function response_history(question_attempt $qa, qbehaviour_renderer $behaviouroutput,
            qtype_renderer $qtoutput, question_display_options $options) {

        if (!$options->history) {
            return '';
        }

        $table = new html_table();
        $table->head  = array (
            get_string('step', 'question'),
            get_string('time'),
            get_string('action', 'question'),
            get_string('state', 'question'),
        );
        if ($options->marks >= question_display_options::MARK_AND_MAX) {
            $table->head[] = get_string('marks', 'question');
        }

        foreach ($qa->get_full_step_iterator() as $i => $step) {
            $stepno = $i + 1;

            $rowclass = '';
            if ($stepno == $qa->get_num_steps()) {
                $rowclass = 'current';
            } else if (!empty($options->questionreviewlink)) {
                $url = new moodle_url($options->questionreviewlink,
                        array('slot' => $qa->get_slot(), 'step' => $i));
                $stepno = $this->output->action_link($url, $stepno,
                        new popup_action('click', $url, 'reviewquestion',
                                array('width' => 450, 'height' => 650)),
                        array('title' => get_string('reviewresponse', 'question')));
            }

            $restrictedqa = new question_attempt_with_restricted_history($qa, $i, null);

            $row = [$stepno,
                    userdate($step->get_timecreated(), get_string('strftimedatetimeshortaccurate', 'core_langconfig')),
                    s($qa->summarise_action($step)) . $this->action_author($step, $options),
                    $restrictedqa->get_state_string($options->correctness)];

            if ($options->marks >= question_display_options::MARK_AND_MAX) {
                $row[] = $qa->format_fraction_as_mark($step->get_fraction(), $options->markdp);
            }

            $table->rowclasses[] = $rowclass;
            $table->data[] = $row;
        }

        return html_writer::tag('h4', get_string('responsehistory', 'question'),
                        array('class' => 'responsehistoryheader')) .
                $options->extrahistorycontent .
                html_writer::tag('div', html_writer::table($table, true),
                        array('class' => 'responsehistoryheader'));
    }

    /**
     * Action author's profile link.
     *
     * @param question_attempt_step $step The step.
     * @param question_display_options $options The display options.
     * @return string The link to user's profile.
     */
    protected function action_author(question_attempt_step $step, question_display_options $options): string {
        if ($options->userinfoinhistory && $step->get_user_id() != $options->userinfoinhistory) {
            return html_writer::link(
                    new moodle_url('/user/view.php', ['id' => $step->get_user_id(), 'course' => $this->page->course->id]),
                    $step->get_user_fullname(), ['class' => 'd-table-cell']);
        } else {
            return '';
        }
    }
}
