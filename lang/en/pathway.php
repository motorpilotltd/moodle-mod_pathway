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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Language strings for mod_pathway.
 *
 * @package    mod_pathway
 * @copyright  2026 Jon Bolton, Simon Lewis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['addmoreoptions'] = 'Add {no} more options';
$string['allowupdate'] = 'Allow the choice to be changed';
$string['allowupdate_help'] = 'If enabled, users may return and select a different option. Any cohort membership added by the previous option is removed first.';
$string['alreadychosen'] = 'Your current choice is: {$a}';
$string['bulkassign'] = 'Assign an option to users';
$string['bulkassigncapped'] = 'This activity is on the site home, so the list shows the first {$a} site users. Use search to find a specific person.';
$string['bulkassigndone'] = 'Assigned {$a->assigned} user(s). Added {$a->cohortadded} to the cohort; {$a->alreadymember} were already members. Skipped {$a->skippedfull} because the option was full.';
$string['bulkassignoption'] = 'Option to assign';
$string['bulkassignsubmit'] = 'Assign option';
$string['bulkassignusers'] = 'Users';
$string['bulkassignusers_help'] = 'The users to assign the chosen option to. Each is treated as if they had chosen it themselves: they are added to the option\'s cohort or group, completion updates, and any downstream availability opens up. Users already in the cohort keep their existing membership, which this activity will not later remove.';
$string['cannotchange'] = 'Your choice has been recorded and cannot be changed.';
$string['changeyourchoice'] = 'Change your choice';
$string['choicedeleted'] = 'Your choice has been cleared.';
$string['choicedeletedother'] = 'The choice has been deleted.';
$string['choicesaved'] = 'Your choice has been saved.';
$string['clearchoice'] = 'Clear my choice';
$string['clicktochoose'] = 'Select an option to save your choice.';
$string['cohortfull'] = 'That option is full. Please choose another.';
$string['completionchoice'] = 'User must make a choice';
$string['completiondetail:choice'] = 'Make a choice';
$string['confirmdeletemembership'] = 'This choice added the user to a cohort or group. Removing a cohort membership can also unenrol the user from other courses that use that cohort. Do you want to remove those memberships too?';
$string['confirmdeleteother'] = 'Are you sure you want to delete this user\'s choice?';
$string['confirmdeleteown'] = 'Are you sure you want to clear your choice? You will be able to choose again.';
$string['confirmfinal'] = 'Are you sure you want to choose "{$a}"? This choice cannot be changed afterwards.';
$string['deleteandkeep'] = 'Delete, keep memberships';
$string['deleteandremove'] = 'Delete and remove memberships';
$string['deletechoice'] = 'Delete choice';
$string['displaymode'] = 'Show on the course page';
$string['displaymode_help'] = 'Controls what appears under the activity name on the course page. "Options as images" shows a tile per option using the image uploaded against it, falling back to the option\'s first letter where no image has been added. Users can select straight from the course page in either inline mode.';
$string['displaymodeimages'] = 'Options as images';
$string['displaymodelink'] = 'Link only';
$string['displaymodetext'] = 'Options as a list';
$string['eventchoicedeleted'] = 'Choice deleted';
$string['eventchoicesaved'] = 'Choice saved';
$string['invalidcohort'] = 'This cohort cannot be used here.';
$string['invalidgroup'] = 'This group does not belong to this course.';
$string['makeyourchoice'] = 'Make your choice';
$string['managecohorts'] = 'Update cohort membership';
$string['managecohorts_help'] = 'If enabled, saving a choice adds the user to the cohort mapped to that option. If a user changes their choice, the earlier membership is removed, but only if this activity added it.';
$string['managegroups'] = 'Update course group membership';
$string['managegroups_help'] = 'If enabled, saving a choice adds the user to the course group mapped to that option. As with cohorts, a membership is only removed later if this activity was the thing that created it.';
$string['manageresponses'] = 'Manage responses';
$string['modulename'] = 'Pathway';
$string['modulename_help'] = 'The pathway activity presents a short list of options. When a user selects one, they can be added automatically to the cohort or course group mapped to that option. Completion and a matching availability restriction can then key off the selection, so later activities can be shown only to people who took a particular route.';
$string['modulenameplural'] = 'Pathways';
$string['nooptions'] = 'No options have been defined for this activity yet.';
$string['nopermissiontochoose'] = 'You do not have permission to make a choice here.';
$string['noresponses'] = 'No one has made a choice yet.';
$string['optioncohort'] = 'Cohort';
$string['optioncohort_help'] = 'The cohort a user joins when they pick this option. Leave as "None" to record the choice without touching cohort membership, which is useful for an opt-out or "none of the above" option.';
$string['optioncohortlabel'] = 'Cohort for option {no}';
$string['optionfull'] = '{$a->text} (full)';
$string['optiongroup'] = 'Group';
$string['optiongroup_help'] = 'The course group a user joins when they pick this option. Groups are local to this course, so this is usually the safer choice where the selection only needs to affect this course. An option can set a cohort, a group, both, or neither.';
$string['optiongrouplabel'] = 'Group for option {no}';
$string['optionheading'] = 'Options';
$string['optionimage'] = 'Image';
$string['optionimage_help'] = 'An optional image shown when the activity is set to display options as images on the course page. One image per option; anything square works best.';
$string['optionimagelabel'] = 'Image for option {no}';
$string['optionmaxanswers'] = 'Limit';
$string['optionmaxanswers_help'] = 'The maximum number of users who may select this option. Leave at 0 for no limit.';
$string['optionmaxanswerslabel'] = 'Limit for option {no}';
$string['optiontext'] = 'Option text';
$string['optiontext_help'] = 'The label shown to users. An option with no cohort and no group still records the choice, and still counts towards completion and any availability restriction that points at it.';
$string['optiontextlabel'] = 'Option {no}';
$string['ownedmembership'] = 'Added to';
$string['pathway:addinstance'] = 'Add a new pathway activity';
$string['pathway:bulkassign'] = 'Assign options to users in bulk';
$string['pathway:choose'] = 'Make a choice';
$string['pathway:deleteanychoice'] = 'Delete any user\'s choice';
$string['pathway:deleteownchoice'] = 'Delete your own choice';
$string['pathway:readresponses'] = 'View the responses of other users';
$string['pluginadministration'] = 'Pathway administration';
$string['pluginname'] = 'Pathway';
$string['privacy:metadata:core_cohort'] = 'Saving a choice can add the user to the cohort mapped to the selected option.';
$string['privacy:metadata:core_group'] = 'Saving a choice can add the user to the course group mapped to the selected option.';
$string['privacy:metadata:pathway_answer'] = 'The option each user selected in a pathway activity.';
$string['privacy:metadata:pathway_answer:cohortadded'] = 'Whether this activity added the resulting cohort membership.';
$string['privacy:metadata:pathway_answer:groupadded'] = 'Whether this activity added the resulting course group membership.';
$string['privacy:metadata:pathway_answer:optionid'] = 'The option the user selected.';
$string['privacy:metadata:pathway_answer:pathwayid'] = 'The activity instance the answer belongs to.';
$string['privacy:metadata:pathway_answer:timemodified'] = 'The time the choice was last saved.';
$string['privacy:metadata:pathway_answer:userid'] = 'The user who made the choice.';
$string['processimages'] = 'Resize uploaded option images';
$string['processimages_desc'] = 'Resize option images on upload to a maximum of 512 pixels on the longest edge. Best effort: an image that cannot be processed is stored exactly as uploaded.';
$string['responsecount'] = '{$a->count} ({$a->percent}%)';
$string['responses'] = 'Responses';
$string['savechoice'] = 'Save choice';
$string['selectanoption'] = 'Select an option';
$string['selected'] = '(currently selected)';
$string['showresults'] = 'Show a response summary';
$string['showresults_help'] = 'If enabled, users see how many people picked each option. Names are never shown to users without the "View the responses of other users" capability.';
$string['tilesize'] = 'Option size';
$string['tilesize_help'] = 'The size of the options on the course page: the button size when showing options as a list, or the tile size when showing options as images. Has no effect in link-only mode. Medium suits most cases; small fits more options across a row, large and extra large give each option more presence.';
$string['tilesizelarge'] = 'Large';
$string['tilesizemedium'] = 'Medium';
$string['tilesizesmall'] = 'Small';
$string['tilesizexlarge'] = 'Extra large';
$string['usewebp'] = 'Convert option images to WebP';
$string['usewebp_desc'] = 'Convert processed option images to the WebP format where the server\'s image library supports it, falling back to JPEG otherwise. Only applies when image resizing is enabled.';
$string['yourchoice'] = 'Your choice';
