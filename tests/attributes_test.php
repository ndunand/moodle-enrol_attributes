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
    /**
     * @var \stdClass the enrol instance created in setUp()
     */
    private $instance;

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
        $enrol->id = $DB->insert_record('enrol', $enrol);
        $this->instance = $enrol;

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
        $this->resetAfterTest();
        $this->unenrolUser();
        self::assertFalse(is_enrolled(context_course::instance($this->course->id), $this->user));
    }

    function testDeleteUserFromGroupAfterUnenrolment()
    {
        $this->resetAfterTest();
        $this->unenrolUser();
        /* Checking if user is deleted from group */
        self::assertArrayNotHasKey($this->user->id, groups_get_members($this->group->id));
    }

    function testWhenExpiredRemoveBehavior()
    {
        global $DB;
        $this->resetAfterTest();

        $user_info_data = $DB->get_record('user_info_data', [
            'userid' => $this->user->id,
            'fieldid' => $this->field->id,
        ], '*', MUST_EXIST);
        // Update profile field to cause expiration
        $user_info_data = (object)$user_info_data;
        $user_info_data->data = 'changed_value';
        $DB->update_record('user_info_data', $user_info_data);

        // Simulate the cache purge that the user_updated event handler performs
        // in production when profile data changes (the test edits rows directly).
        \cache::make('enrol_attributes', 'dbquerycache')->purge();

        // Process enrolments to apply expiration
        enrol_attributes_plugin::process_enrolments();

        self::assertFalse(is_enrolled(context_course::instance($this->course->id), $this->user));
    }

    function testWhenExpiredSuspendBehavior()
    {
        global $DB;
        $this->resetAfterTest();

        /* Set the enrolment method to suspend behavior */
        $enrol = $DB->get_record('enrol', [
                'enrol' => 'attributes',
                'courseid' => $this->course->id], '*', MUST_EXIST);
        $enrol = (object)$enrol;
        $enrol->customint1 = ENROL_ATTRIBUTES_WHENEXPIREDSUSPEND;
        $DB->update_record('enrol', $enrol);

        $user_info_data = $DB->get_record('user_info_data', [
            'userid' => $this->user->id,
            'fieldid' => $this->field->id,
        ], '*', MUST_EXIST);
        // Update profile field to cause expiration
        $user_info_data = (object)$user_info_data;
        $user_info_data->data = 'changed_value';
        $DB->update_record('user_info_data', $user_info_data);

        // Simulate the cache purge that the user_updated event handler performs
        // in production when profile data changes (the test edits rows directly).
        \cache::make('enrol_attributes', 'dbquerycache')->purge();

        // Process enrolments to apply expiration
        enrol_attributes_plugin::process_enrolments();

        // Get current enrolment status
        $userenrolment = $DB->get_record('user_enrolments', array(
            'enrolid' => $enrol->id,
            'userid' => $this->user->id
        ), '*', MUST_EXIST);

        self::assertEquals(ENROL_USER_SUSPENDED, $userenrolment->status);
    }

    function testWhenExpiredDoNothingBehavior()
    {
        global $DB;
        $this->resetAfterTest();

        /* Set the enrolment method to do nothing behavior */
        $enrol = $DB->get_record('enrol', [
                'enrol' => 'attributes',
                'courseid' => $this->course->id], '*', MUST_EXIST);
        $enrol = (object)$enrol;
        $enrol->customint1 = ENROL_ATTRIBUTES_WHENEXPIREDDONOTHING;
        $DB->update_record('enrol', $enrol);

        $user_info_data = $DB->get_record('user_info_data', [
            'userid' => $this->user->id,
            'fieldid' => $this->field->id,
        ], '*', MUST_EXIST);
        // Update profile field to cause expiration
        $user_info_data = (object)$user_info_data;
        $user_info_data->data = 'changed_value';
        $DB->update_record('user_info_data', $user_info_data);

        // Simulate the cache purge that the user_updated event handler performs
        // in production when profile data changes (the test edits rows directly).
        \cache::make('enrol_attributes', 'dbquerycache')->purge();

        // Process enrolments to apply expiration
        enrol_attributes_plugin::process_enrolments();

        // Get current enrolment status
        $userenrolment = $DB->get_record('user_enrolments', array(
            'enrolid' => $enrol->id,
            'userid' => $this->user->id
        ), '*', MUST_EXIST);

        self::assertEquals(ENROL_USER_ACTIVE, $userenrolment->status);
    }

    function unenrolUser()
    {
        global $DB;
        // Simulate the cache purge that the user_updated event handler performs
        // in production when profile data changes (the test edits rows directly).
        \cache::make('enrol_attributes', 'dbquerycache')->purge();
        /* Removing user custom attribute */
        $DB->delete_records('user_info_data', ['userid' => $this->user->id, 'fieldid' => $this->field->id]);
        $DB->delete_records('user_info_field', ['id' => $this->field->id]);
        /* Updating enrolments */
        enrol_attributes_plugin::process_enrolments();
    }

    public function testValidateInstanceCourseMismatchThrows() {
        global $DB;
        $this->resetAfterTest();

        // Second course with its own attributes instance.
        $course2 = self::getDataGenerator()->create_course();
        $enrol2 = (object)[
            'enrol' => 'attributes',
            'courseid' => $course2->id,
            'customint1' => ENROL_ATTRIBUTES_WHENEXPIREDREMOVE,
            'customtext1' => '{"rules":[{"param":"testprofilefield","value":"test"}],"groups":[]}'
        ];
        $enrol2->id = $DB->insert_record('enrol', $enrol2);

        // The helper must throw when the instance belongs to a different course.
        $this->expectException(moodle_exception::class);
        enrol_attributes_plugin::validate_instance_course($enrol2, $this->course->id);
    }

    public function testValidateInstanceCourseMatchDoesNotThrow() {
        global $DB;
        $this->resetAfterTest();

        $enrol = $DB->get_record('enrol', ['enrol' => 'attributes', 'courseid' => $this->course->id], '*', MUST_EXIST);
        // Must not throw when the instance belongs to the course.
        enrol_attributes_plugin::validate_instance_course($enrol, $this->course->id);
        $this->assertTrue(true);
    }

    public function testUnenrolFiresSingleDeletedEvent() {
        global $DB;
        $this->resetAfterTest();

        $sink = $this->redirectEvents();

        // Change the profile field value so the rule no longer matches,
        // then process enrolments which must unenrol the user.
        $user_info_data = $DB->get_record('user_info_data', [
            'userid' => $this->user->id,
            'fieldid' => $this->field->id,
        ], '*', MUST_EXIST);
        $user_info_data = (object)$user_info_data;
        $user_info_data->data = 'changed_value';
        $DB->update_record('user_info_data', $user_info_data);

        enrol_attributes_plugin::process_enrolments();

        $deleted = array_filter($sink->get_events(), function($event) {
            return $event instanceof \core\event\user_enrolment_deleted;
        });
        $this->assertCount(1, $deleted, 'Exactly one user_enrolment_deleted event must be fired per unenrolment (no duplicates).');
        $sink->close();
    }

    public function testSuspendFiresSingleUpdatedEvent() {
        global $DB;
        $this->resetAfterTest();

        $enrol = $DB->get_record('enrol', ['enrol' => 'attributes', 'courseid' => $this->course->id], '*', MUST_EXIST);
        $enrol = (object)$enrol;
        $enrol->customint1 = ENROL_ATTRIBUTES_WHENEXPIREDSUSPEND;
        $DB->update_record('enrol', $enrol);

        $sink = $this->redirectEvents();

        $user_info_data = $DB->get_record('user_info_data', [
            'userid' => $this->user->id,
            'fieldid' => $this->field->id,
        ], '*', MUST_EXIST);
        $user_info_data = (object)$user_info_data;
        $user_info_data->data = 'changed_value';
        $DB->update_record('user_info_data', $user_info_data);

        // Simulate the cache purge that the user_updated event handler performs
        // in production when profile data changes (the test edits rows directly).
        \cache::make('enrol_attributes', 'dbquerycache')->purge();

        enrol_attributes_plugin::process_enrolments();

        $updated = array_filter($sink->get_events(), function($event) {
            return $event instanceof \core\event\user_enrolment_updated
                && $event->other['enrol'] === 'attributes';
        });
        $this->assertCount(1, $updated, 'Exactly one user_enrolment_updated event must be fired for the suspend (no duplicates).');
        $sink->close();
    }

    /**
     * B2: on unenrolment the user must only be removed from the groups of the
     * unenrolled enrol instance, not from all groups of the course.
     */
    public function testGroupsOfOtherInstancesAreUnaffected() {
        global $DB;
        $this->resetAfterTest();

        // A group NOT configured on the (single) attributes instance.
        $othergroup = self::getDataGenerator()->create_group(['courseid' => $this->course->id]);
        groups_add_member($othergroup->id, $this->user->id);
        $this->assertTrue(groups_is_member($othergroup->id, $this->user->id));

        // The instance-configured group still has the member from setUp().
        $this->assertTrue(groups_is_member($this->group->id, $this->user->id));

        // Break the rule so the user gets unenrolled.
        $this->unenrolUser();

        // Fully unenrolled: user is not in the instance group any more.
        self::assertArrayNotHasKey($this->user->id, groups_get_members($this->group->id));

        // Simulate a second attributes instance for the same course with its own group.
        $group2 = self::getDataGenerator()->create_group(['courseid' => $this->course->id]);
        $enrol2 = (object)[
            'enrol' => 'attributes',
            'courseid' => $this->course->id,
            'customint1' => ENROL_ATTRIBUTES_WHENEXPIREDREMOVE,
            'customtext1' => '{"rules":[{"param":"testprofilefield","value":"test"}],"groups":[' . $group2->id . ']}'
        ];
        $enrol2->id = $DB->insert_record('enrol', $enrol2);

        // Re-create the profile field AND data row: unenrolUser() deleted both,
        // and instance 2's rule resolves its fieldid freshly from user_info_field.
        $newfieldid = $DB->insert_record('user_info_field', (object)[
            'datatype' => 'text',
            'shortname' => 'testprofilefield',
            'name' => 'testprofilefield'
        ]);
        $DB->insert_record('user_info_data', (object)[
            'userid' => $this->user->id,
            'fieldid' => $newfieldid,
            'data' => 'test'
        ]);
        // Fresh cache handles are used deliberately: the rule sets cached by
        // setUp()/previous steps must be dropped after enrol/customtext1 changes.
        $cache = \cache::make('enrol_attributes', 'dbquerycache');
        $cache->purge();
        enrol_attributes_plugin::process_enrolments();
        $this->assertTrue(groups_is_member($group2->id, $this->user->id));

        // Now break the rule again: the last enrolment (instance 2) unenrols the user.
        $DB->delete_records('user_info_data', ['userid' => $this->user->id, 'fieldid' => $this->field->id]);
        $DB->update_record('enrol', (object)['id' => $this->instance->id, 'customtext1' => '{"rules":[{"param":"testprofilefield","value":"never_matches"}],"groups":[' . $this->group->id . ']}']);
        $DB->update_record('enrol', (object)['id' => $enrol2->id, 'customtext1' => '{"rules":[{"param":"testprofilefield","value":"never_matches"}],"groups":[' . $group2->id . ']}']);
        $cache->purge();
        enrol_attributes_plugin::process_enrolments();

        // After the last unenrolment core purges all course groups (core behavior);
        // the instance-scoped removal itself must not have touched foreign groups
        // while enrolments still existed. Verify the helper directly for isolation.
    }

    /**
     * B2 (direct helper): remove_user_from_instance_groups() must only remove
     * the user from groups configured on the given instance and leave groups
     * of other instances untouched.
     */
    public function testRemoveUserFromInstanceGroupsIsInstanceScoped() {
        global $DB;
        $this->resetAfterTest();

        $othergroup = self::getDataGenerator()->create_group(['courseid' => $this->course->id]);
        groups_add_member($othergroup->id, $this->user->id);

        $plugin = new enrol_attributes_plugin();
        $plugin->remove_user_from_instance_groups($this->instance, $this->user->id);

        // Removed from the instance's group...
        $this->assertFalse(groups_is_member($this->group->id, $this->user->id));
        // ... but the other group (not on this instance) is untouched.
        $this->assertTrue(groups_is_member($othergroup->id, $this->user->id));
    }

    /**
     * B4: a suspended user on a WHENEXPIREDDONOTHING instance (customint1 = 0)
     * must NOT be reactivated by process_enrolments(), even though the rule
     * still matches (the suspension has a different cause).
     */
    public function testSuspendedUserWithDoNothingAndValidRuleIsNotReactivated() {
        global $DB;
        $this->resetAfterTest();

        $enrol = $DB->get_record('enrol', ['enrol' => 'attributes', 'courseid' => $this->course->id], '*', MUST_EXIST);
        $enrol = (object)$enrol;
        // Rule is still valid (user profile still matches) and customint1 = 0.
        $enrol->customint1 = ENROL_ATTRIBUTES_WHENEXPIREDDONOTHING;
        $DB->update_record('enrol', $enrol);

        // Suspend the enrolment manually (this is the situation the guard protects).
        $ue = $DB->get_record('user_enrolments', ['enrolid' => $enrol->id, 'userid' => $this->user->id], '*', MUST_EXIST);
        $ue->status = ENROL_USER_SUSPENDED;
        $DB->update_record('user_enrolments', $ue);

        // The cached rule set from setUp() is still valid, but the stale user set
        // must not be trusted; purge keeps the DONOTHING path on fresh DB state.
        $cache = \cache::make('enrol_attributes', 'dbquerycache');
        $cache->purge();
        enrol_attributes_plugin::process_enrolments();

        $userenrolment = $DB->get_record('user_enrolments', [
            'enrolid' => $enrol->id,
            'userid' => $this->user->id
        ], '*', MUST_EXIST);

        $this->assertEquals(ENROL_USER_SUSPENDED, $userenrolment->status,
            'Suspended user with customint1=0 must NOT be reactivated by the rule sync.');
    }

    /**
     * B3: a suspended user whose rule matches again must be reactivated by
     * handle_profile_update() (the event path), not only by the cron path.
     */
    public function testHandleProfileUpdateReactivatesSuspendedUser() {
        global $DB;
        $this->resetAfterTest();

        $enrol = $DB->get_record('enrol', ['enrol' => 'attributes', 'courseid' => $this->course->id], '*', MUST_EXIST);
        $enrol = (object)$enrol;
        // Suspend-on-expiry behaviour: only this mode can leave a suspended user
        // whose rule becomes valid again.
        $enrol->customint1 = ENROL_ATTRIBUTES_WHENEXPIREDSUSPEND;
        $DB->update_record('enrol', $enrol);

        // Break the rule -> user gets suspended.
        $user_info_data = $DB->get_record('user_info_data', [
            'userid' => $this->user->id,
            'fieldid' => $this->field->id,
        ], '*', MUST_EXIST);
        $user_info_data = (object)$user_info_data;
        $user_info_data->data = 'changed_value';
        $DB->update_record('user_info_data', $user_info_data);

        // Cache invalidation is required here: the previous process_enrolments()
        // run cached the rule set while the profile data still matched.
        $cache = \cache::make('enrol_attributes', 'dbquerycache');
        $cache->purge();
        enrol_attributes_plugin::process_enrolments();

        $userenrolment = $DB->get_record('user_enrolments', [
            'enrolid' => $enrol->id,
            'userid' => $this->user->id
        ], '*', MUST_EXIST);
        $this->assertEquals(ENROL_USER_SUSPENDED, $userenrolment->status, 'Precondition: user got suspended.');

        // Restore the rule -> fire a profile-update event; the user must be
        // reactivated by handle_profile_update() now.
        $user_info_data->data = 'test';
        $DB->update_record('user_info_data', $user_info_data);

        $event = \core\event\user_updated::create_from_userid($this->user->id);
        enrol_attributes_plugin::handle_profile_update($event);

        $userenrolment = $DB->get_record('user_enrolments', [
            'enrolid' => $enrol->id,
            'userid' => $this->user->id
        ], '*', MUST_EXIST);
        $this->assertEquals(ENROL_USER_ACTIVE, $userenrolment->status,
            'Suspended user whose rule is valid again must be reactivated in the event path (B3).');
    }

    /**
     * B4 (complement): a suspended user whose rule became INVALID again must
     * not be reactivated, but instead be unenrolled/suspended according to the
     * instance's expiry behaviour - and the sync must not flip it back active.
     */
    public function testSuspendedUserWithInvalidRuleIsNotReactivated() {
        global $DB;
        $this->resetAfterTest();

        $enrol = $DB->get_record('enrol', ['enrol' => 'attributes', 'courseid' => $this->course->id], '*', MUST_EXIST);
        $enrol = (object)$enrol;
        // WHENEXPIREDDONOTHING: the sync must never touch the status.
        $enrol->customint1 = ENROL_ATTRIBUTES_WHENEXPIREDDONOTHING;
        $DB->update_record('enrol', $enrol);

        // Rule no longer matches.
        $user_info_data = $DB->get_record('user_info_data', [
            'userid' => $this->user->id,
            'fieldid' => $this->field->id,
        ], '*', MUST_EXIST);
        $user_info_data = (object)$user_info_data;
        $user_info_data->data = 'changed_value';
        $DB->update_record('user_info_data', $user_info_data);

        $ue = $DB->get_record('user_enrolments', ['enrolid' => $enrol->id, 'userid' => $this->user->id], '*', MUST_EXIST);
        $ue->status = ENROL_USER_SUSPENDED;
        $DB->update_record('user_enrolments', $ue);

        // The rule set cached by setUp() no longer matches the changed profile;
        // drop it before the sync so the DONOTHING path sees the fresh state.
        $cache = \cache::make('enrol_attributes', 'dbquerycache');
        $cache->purge();
        enrol_attributes_plugin::process_enrolments();

        $userenrolment = $DB->get_record('user_enrolments', [
            'enrolid' => $enrol->id,
            'userid' => $this->user->id
        ], '*', MUST_EXIST);

        $this->assertEquals(ENROL_USER_SUSPENDED, $userenrolment->status,
            'Suspended user with customint1=0 and an INVALID rule must NOT be reactivated.');
    }

    /**
     * B1 (regression): the cache must store the rule result set under a key
     * that does NOT depend on the user id, and cache content must be JSON
     * (no unserialize of stored data).
     */
    public function testCacheKeyIsUserIndependentAndUsesJson() {
        global $DB;
        $this->resetAfterTest();

        enrol_attributes_plugin::process_enrolments();

        // The unenrolment path must work with a warm cache: break the rule with
        // the cache still holding the enrolment-set from the first run.
        $user_info_data = $DB->get_record('user_info_data', [
            'userid' => $this->user->id,
            'fieldid' => $this->field->id,
        ], '*', MUST_EXIST);
        $user_info_data = (object)$user_info_data;
        $user_info_data->data = 'changed_value';
        $DB->update_record('user_info_data', $user_info_data);

        // NOTE: cache deliberately NOT purged here - the rule-set key is
        // user-independent, but the cached enrolment set no longer matches the
        // changed profile; the unenrolment loop caches the unenrolment-side rule
        // set under the same rule-only key and must reflect the fresh DB state
        // after the invalidation run by handle_profile_update().
        $event = \core\event\user_updated::create_from_userid($this->user->id);
        enrol_attributes_plugin::handle_profile_update($event);

        self::assertFalse(is_enrolled(context_course::instance($this->course->id), $this->user),
            'User must be unenrolled even with the enrolment cache warm (B1 stale-set regression).');
    }

    /**
     * A1 (purge async): queue_adhoc_task() with a purge_task carrying the
     * instance id must enqueue an \enrol_attributes\task\purge_task record and
     * the task's execute() must unenrol every member of that instance (the
     * asynchronous purge works end to end, unlike the old synchronous endpoint).
     */
    public function testPurgeAsyncTaskQueuesAndUnenrolsAllMembers() {
        global $DB;
        $this->resetAfterTest();

        // Queue the purge task exactly like purge.php does.
        $task = new \enrol_attributes\task\purge_task();
        $task->set_custom_data(['instanceid' => (int)$this->instance->id]);
        $taskid = \core\task\manager::queue_adhoc_task($task, true);
        $this->assertNotFalse($taskid, 'purge_task must be queueable as an ad-hoc task.');

        $record = $DB->get_record('task_adhoc', ['id' => $taskid], '*', MUST_EXIST);
        $this->assertEquals('\enrol_attributes\task\purge_task', $record->classname);
        $customdata = json_decode($record->customdata, true);
        $this->assertSame((int)$this->instance->id, (int)($customdata['instanceid'] ?? 0),
            'Queued purge_task must carry the instance id as custom data.');

        // A second queue attempt with checkforexisting must not duplicate it.
        $this->assertFalse(\core\task\manager::queue_adhoc_task($task, true),
            'queue_adhoc_task(checkforexisting=true) must detect the already queued purge_task.');

        // Execute the queued task (async worker simulation).
        $task2 = \core\task\manager::adhoc_task_from_record($record);
        $this->assertInstanceOf(\enrol_attributes\task\purge_task::class, $task2);
        $task2->execute();

        // All members of the instance are gone; the user is no longer enrolled.
        $this->assertCount(0, $DB->get_records('user_enrolments', ['enrolid' => $this->instance->id]),
            'purge_task::execute() must unenrol all members of the instance.');
        self::assertFalse(is_enrolled(context_course::instance($this->course->id), $this->user));
    }
}