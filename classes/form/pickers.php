<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Shared mform picker helpers (user + cohort) used across the Vbounds/Sternfast plugin suite.
 *
 * Extracted from report_gradelookup and local_brandmask so the pattern — and its bug fixes — live
 * in one place. Consuming plugins declare $plugin->dependencies['local_vboundslib'] in version.php.
 *
 * @package    local_vboundslib
 * @copyright  2026 Vbounds LLC (Sternfast)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_vboundslib\form;

/**
 * Factory methods for the common targeting pickers.
 */
class pickers {
    /**
     * Options for an mform 'autocomplete' element that searches ALL site users over AJAX, with a
     * chip rendered for each already-selected user. Use only on capability-gated / admin pages —
     * the core_user/form_user_selector backend requires moodle/user:viewalldetails.
     *
     * Usage:
     *   $mform->addElement('autocomplete', 'userids', $label, [],
     *       \local_vboundslib\form\pickers::user_autocomplete_options($context, $noselection));
     *   $mform->setType('userids', PARAM_INT);
     *
     * @param \context $context Context used for name/identity formatting.
     * @param string $noselectionstring Placeholder shown when nothing is selected.
     * @return array The options array for the autocomplete element.
     */
    public static function user_autocomplete_options(\context $context, string $noselectionstring): array {
        return [
            'ajax'              => 'core_user/form_user_selector',
            'multiple'          => true,
            'noselectionstring' => $noselectionstring,
            'valuehtmlcallback' => function ($userid) use ($context) {
                global $OUTPUT;
                // The allowcustom=false flag keeps identity fields on the {user} table (no joins), so passing
                // get_sql()->selects to get_user() is safe. A custom profile field in "Show user
                // identity" would otherwise need a joined query and fatal the edit page.
                $fields = \core_user\fields::for_name()->with_identity($context, false);
                $record = \core_user::get_user($userid, 'id ' . $fields->get_sql()->selects, IGNORE_MISSING);
                if (!$record) {
                    return false;
                }
                $user = (object) [
                    'id'          => $record->id,
                    'fullname'    => fullname($record, has_capability('moodle/site:viewfullnames', $context)),
                    'extrafields' => [],
                ];
                foreach ($fields->get_required_fields([\core_user\fields::PURPOSE_IDENTITY]) as $extrafield) {
                    $user->extrafields[] = (object) ['name' => $extrafield, 'value' => s($record->$extrafield)];
                }
                return $OUTPUT->render_from_template('core_user/form_user_selector_suggestion', $user);
            },
        ];
    }

    /**
     * id => label map of all cohorts for a static multi-select 'autocomplete', each label carrying
     * the cohort ID number (when distinct from the name) and its member count.
     *
     * @return array cohortid => label.
     */
    public static function cohort_options(): array {
        global $DB;
        $out = [];
        $records = $DB->get_records_sql('
            SELECT c.id, c.name, c.idnumber, COUNT(cm.id) AS members
              FROM {cohort} c
         LEFT JOIN {cohort_members} cm ON cm.cohortid = c.id
          GROUP BY c.id, c.name, c.idnumber
          ORDER BY c.name ASC');
        foreach ($records as $c) {
            $label = format_string($c->name);
            if ((string) $c->idnumber !== '' && strcasecmp((string) $c->idnumber, $c->name) !== 0) {
                $label .= ' [' . s($c->idnumber) . ']';
            }
            $label .= ' · ' . get_string('cohortmembers', 'local_vboundslib', $c->members);
            $out[$c->id] = $label;
        }
        return $out;
    }
}
