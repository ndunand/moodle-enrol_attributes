<?php

global $CFG;

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.'); //  It must be included from a Moodle page
}

require_once $CFG->dirroot . '/enrol/attributes/lib.php';

class attributes_test extends advanced_testcase
{
    /**
     * @var \stdClass
     */
    private $course;
    /**
     * @var \stdClass
     */
    private $group;
    /**
     * @var \stdClass
     */
    private $field;
    /**
     * @var \stdClass
     */
    private $user;

    protected function setUp(): void
    {
        global $DB;

        $this->course = self::getDataGenerator()->create_course();
        $this->group = self::getDataGenerator()->create_group(['courseid' => $this->course->id]);

        // Create a new profile field.
        $new_rec = array(
            'datatype' => 'text',
            'shortname' => 'testprofilefield',
            'name' => 'testprofilefield'
        );
        $DB->insert_record('user_info_field', (object)$new_rec);

        // name is not searcheable in the database, it must be removed before reading.
        unset($new_rec['name']);
        $this->field = $DB->get_record('user_info_field', $new_rec);

        $this->user = self::getDataGenerator()->create_user(
            [
                'username' => 'toto@example.com',
                'email' => 'toto@example.com',
                'auth' => 'shibboleth',
            ]
        );

        /* Set configuration (enrol attributes) */
        set_config( 'profilefields', 'testprofilefield', 'enrol_attributes');

        /* Creating link between user and custom user field */
        $user_info_data = (object)[
            'userid' => $this->user->id,
            'fieldid' => $this->field->id,
            'data' => 'test'
        ];
        $DB->insert_record('user_info_data', $user_info_data);

        /* Creating a new enrolment */
        $enrol = (object)[
            'enrol' => 'attributes',
            'courseid' => $this->course->id,
            'customint1' => ENROL_ATTRIBUTES_WHENEXPIREDREMOVE,
            'customtext1' => '{"rules":[{"param":"testprofilefield","value":"test"}],"groups":[' . $this->group->id . ']}'
        ];
        $DB->insert_record('enrol', $enrol);

        /* Actually enrolling the user */
        enrol_attributes_plugin::process_enrolments();
    }

    public function testAddUserEnrolByGroup()
    {
        $this->resetAfterTest();
        self::assertArrayHasKey($this->user->id, groups_get_members($this->group->id));
    }

    public function testEnrolUser(){
        $this->resetAfterTest();
        self::assertTrue(is_enrolled(context_course::instance($this->course->id), $this->user));
    }

    public function testUnenrolUser(){
        //Simulating the invalidatecache task run by the cron
        $cache = \cache::make('enrol_attributes', 'dbquerycache');
        $cache->purge();

        $this->resetAfterTest();
        $this->unenrolUser();
        self::assertFalse(is_enrolled(context_course::instance($this->course->id), $this->user));
    }

    function testDeleteUserFromGroupAfterUnenrolment()
    {
        //Simulating the invalidatecache task run by the cron
        $cache = \cache::make('enrol_attributes', 'dbquerycache');
        $cache->purge();

        $this->resetAfterTest();
        $this->unenrolUser();
        /* Checking if user is deleted from group */
        self::assertArrayNotHasKey($this->user->id, groups_get_members($this->group->id));
    }

    function unenrolUser()
    {
        global $DB;
        /* Removing user custom attribute */
        $DB->delete_records('user_info_data', ['userid' => $this->user->id, 'fieldid' => $this->field->id]);
        $DB->delete_records('user_info_field', ['id' => $this->field->id]);
        /* Updating enrolments */
        enrol_attributes_plugin::process_enrolments();
    }
}

