<?php
/* For licensing terms, see /license.txt */

//@todo this could be integrated in the inc/lib/model.lib.php + try to clean this file

$language_file = array('admin', 'exercice', 'gradebook', 'tracking');

require_once '../global.inc.php';

$libpath = api_get_path(LIBRARY_PATH);

// 1. Setting variables needed by jqgrid

$action = $_GET['a'];
$page   = intval($_REQUEST['page']); //page
$limit  = intval($_REQUEST['rows']); //quantity of rows
$sidx   = $_REQUEST['sidx'];         //index (field) to filter
$sord   = $_REQUEST['sord'];         //asc or desc

if (strpos(strtolower($sidx), 'asc') !== false) {
    $sidx = str_replace(array('asc', ','), '', $sidx);
    $sord = 'asc';
}

if (strpos(strtolower($sidx), 'desc') !== false) {
    $sidx = str_replace(array('desc', ','), '', $sidx);
    $sord = 'desc';
}

if (!in_array($sord, array('asc','desc'))) {
    $sord = 'desc';
}

// Actions allowed to other roles.
if (!in_array(
    $action,
    array(
        'get_exercise_results',
        'get_hotpotatoes_exercise_results',
        'get_work_teacher',
        'get_work_student',
        'get_work_user_list',
        'get_work_user_list_others',
        'get_work_user_list_all',
        'get_timelines',
        'get_user_skill_ranking',
        'get_usergroups_teacher',
        'get_user_course_report_resumed',
        'get_user_course_report',
        'get_sessions_tracking'
    )
)) {
    api_protect_admin_script(true);
}

// Search features

$ops = array (
    'eq' => '=',        //equal
    'ne' => '<>',       //not equal
    'lt' => '<',        //less than
    'le' => '<=',       //less than or equal
    'gt' => '>',        //greater than
    'ge' => '>=',       //greater than or equal
    'bw' => 'LIKE',     //begins with
    'bn' => 'NOT LIKE', //doesn't begin with
    'in' => 'LIKE',     //is in
    'ni' => 'NOT LIKE', //is not in
    'ew' => 'LIKE',     //ends with
    'en' => 'NOT LIKE', //doesn't end with
    'cn' => 'LIKE',     //contains
    'nc' => 'NOT LIKE'  //doesn't contain
);

//@todo move this in the display_class or somewhere else

function get_where_clause($col, $oper, $val)
{
    global $ops;
    if (empty($col)) {
        return '';
    }
    if ($oper == 'bw' || $oper == 'bn') {
        $val .= '%';
    }
    if ($oper == 'ew' || $oper == 'en') {
        $val = '%'.$val;
    }
    if ($oper == 'cn' || $oper == 'nc' || $oper == 'in' || $oper == 'ni') {
        $val = '%'.$val.'%';
    }
    $val = Database::escape_string($val);
    return " $col {$ops[$oper]} '$val' ";
}

$where_condition = ""; //if there is no search request sent by jqgrid, $where should be empty

$operation    = isset($_REQUEST['oper'])  ? $_REQUEST['oper']  : false;
$export_format    = isset($_REQUEST['export_format'])  ? $_REQUEST['export_format']  : 'csv';

$search_field    = isset($_REQUEST['searchField'])  ? $_REQUEST['searchField']  : false;
$search_oper     = isset($_REQUEST['searchOper'])   ? $_REQUEST['searchOper']   : false;
$search_string   = isset($_REQUEST['searchString']) ? $_REQUEST['searchString'] : false;

if ($_REQUEST['_search'] == 'true') {
    $where_condition = ' 1 = 1 ';
    $where_condition_in_form = get_where_clause($search_field, $search_oper, $search_string);

    if (!empty($where_condition_in_form)) {
        $where_condition .= ' AND '.$where_condition_in_form;
    }

    $filters   = isset($_REQUEST['filters']) ? json_decode($_REQUEST['filters']) : false;
    if (!empty($filters)) {
        $where_condition .= ' AND ( ';
        $counter = 0;
        foreach ($filters->rules as $key => $rule) {
            $where_condition .= get_where_clause($rule->field, $rule->op, $rule->data);

            if ($counter < count($filters->rules) -1) {
                $where_condition .= $filters->groupOp;
            }
            $counter++;
        }
        $where_condition .= ' ) ';
    }
}

// get index row - i.e. user click to sort $sord = $_GET['sord'];
// get the direction
if (!$sidx) {
    $sidx = 1;
}

//2. Selecting the count FIRST
//@todo rework this

switch ($action) {
    case 'get_user_course_report':
    case 'get_user_course_report_resumed':

        if (!(api_is_platform_admin(false, true))) {
            exit;
        }
        $courseCodeList = array();
        $userIdList  = array();
        if (api_is_drh()) {
            $userList = UserManager::get_users_followed_by_drh(api_get_user_id());
            if (!empty($userList)) {
                $userIdList = array_keys($userList);
            }
            $courseList = CourseManager::get_courses_followed_by_drh(api_get_user_id());
            if (!empty($courseList)) {
                $courseCodeList = array_keys($courseList);
            }

            if (empty($userIdList) || empty($courseCodeList)) {
                exit;
            }
        }
        if ($action == 'get_user_course_report') {
            $count = CourseManager::get_count_user_list_from_course_code(false, null, $courseCodeList, $userIdList);
        } else {
            $count = CourseManager::get_count_user_list_from_course_code(
                true,
                array('ruc'),
                $courseCodeList,
                $userIdList
            );
        }
        break;
    case 'get_course_exercise_medias':
        $course_id = api_get_course_int_id();
        $count = Question::get_count_course_medias($course_id);
        break;
    case 'get_user_skill_ranking':
        $skill = new Skill();
        $count = $skill->get_user_list_skill_ranking_count();
        break;
    case 'get_work_teacher':
        require_once api_get_path(SYS_CODE_PATH).'work/work.lib.php';
        $count = getWorkListTeacher($start, $limit, $sidx, $sord, $where_condition, true);
        break;
    case 'get_work_student':
        require_once api_get_path(SYS_CODE_PATH).'work/work.lib.php';
        $count = getWorkListStudent($start, $limit, $sidx, $sord, $where_condition, true);

        break;
    case 'get_work_user_list_all':
        require_once api_get_path(SYS_CODE_PATH).'work/work.lib.php';
        $work_id = $_REQUEST['work_id'];
        $count = get_count_work($work_id);
        break;
    case 'get_work_user_list_others':
        require_once api_get_path(SYS_CODE_PATH).'work/work.lib.php';
        $work_id = $_REQUEST['work_id'];
        $count = get_count_work($work_id, api_get_user_id());
        break;
    case 'get_work_user_list':
        require_once api_get_path(SYS_CODE_PATH).'work/work.lib.php';
        $work_id = $_REQUEST['work_id'];
        $courseInfo = api_get_course_info();

        $documents = getAllDocumentToWork($work_id, api_get_course_int_id());

        if (empty($documents)) {
            $where_condition .= " AND u.user_id = ".api_get_user_id();
            $count = get_work_user_list($start, $limit, $sidx, $sord, $work_id, $where_condition, null, true);
        } else {
            $count = get_work_user_list_from_documents(
                $start,
                $limit,
                $sidx,
                $sord,
                $work_id,
                api_get_user_id(),
                $where_condition,
                true
            );
        }
/*
        // All
        if ($courseInfo['show_score'] == '0') {
            $count = get_count_work($work_id, null, api_get_user_id());
        } else {
            // Only my stuff
            $count = get_count_work($work_id, api_get_user_id());
        }
*/
        break;
    case 'get_exercise_results':
        require_once api_get_path(SYS_CODE_PATH).'exercice/exercise.lib.php';
        $exercise_id = $_REQUEST['exerciseId'];

        if (isset($_GET['filter_by_user']) && !empty($_GET['filter_by_user'])) {
            $filter_user = intval($_GET['filter_by_user']);
            if ($where_condition == "") {
                $where_condition .= " te.exe_user_id  = '$filter_user'" ;
            } else {
                $where_condition .= " AND te.exe_user_id  = '$filter_user'";
            }
        }
        $count = get_count_exam_results($exercise_id, $where_condition);
        break;
    case 'get_hotpotatoes_exercise_results':
        require_once api_get_path(SYS_CODE_PATH).'exercice/exercise.lib.php';
        $hotpot_path = $_REQUEST['path'];
        $count = get_count_exam_hotpotatoes_results($hotpot_path);
        break;
    case 'get_sessions_tracking':
        if (api_is_drh()) {
            $count = SessionManager::get_sessions_followed_by_drh(api_get_user_id(), null, null, true);
        } else {
            // Sessions for the coach
            $count = Tracking::get_sessions_coached_by_user(api_get_user_id(), null, null, true);
        }
        break;
    case 'get_sessions':
        $courseId = isset($_GET['course_id']) && !empty($_GET['course_id']) ? intval($_GET['course_id']) : null;
        if (!empty($courseId)) {
            $where_condition .= " c.id = $courseId";
        }
        $count = SessionManager::get_count_admin($where_condition);
        break;
    case 'get_session_lp_progress':
    case 'get_session_progress':
        //@TODO replace this for a more efficient function (not retrieving the whole data)
        $course = api_get_course_info_by_id($courseId);
        $users = CourseManager::get_student_list_from_course_code($course['code'], true, intval($_GET['session_id']));
        $count = count($users);
        break;
    case 'get_exercise_progress':
        //@TODO replace this for a more efficient function (not retrieving the whole data)
        $records = Tracking::get_exercise_progress(intval($_GET['session_id']), intval($_GET['course_id']), intval($_GET['exercise_id']));
        $count = count($records);
        break;
    case 'get_session_access_overview':
        //@TODO replace this for a more efficient function (not retrieving the whole data)
        $records = SessionManager::get_user_data_access_tracking_overview($_GET['session_id'], $_GET['course_id'], $_GET['student_id'], $_GET['profile'], $_GET['date_to'], $_GET['date_from'], $options);
        $count = count($records);
        break;
    case 'get_survey_overview':
        //@TODO replace this for a more efficient function (not retrieving the whole data)
        $records = SessionManager::get_survey_overview(intval($_GET['session_id']), intval($_GET['course_id']), intval($_GET['survey_id']), $options);
        $count = count($records);
        break;
    /*case 'get_extra_fields':
        $type = $_REQUEST['type'];
        $obj = new ExtraField($type);
        $count = $obj->get_count();
        break;
    case 'get_extra_field_options':
        $type = $_REQUEST['type'];
        $field_id = $_REQUEST['field_id'];
        $obj = new ExtraFieldOption($type);
        $count = $obj->get_count_by_field_id($field_id);
        break;*/
    case 'get_timelines':
        require_once $libpath.'timeline.lib.php';
        $obj        = new Timeline();
        $count      = $obj->get_count();
        break;
    case 'get_gradebooks':
        require_once $libpath.'gradebook.lib.php';
        $obj        = new Gradebook();
        $count      = $obj->get_count();
        break;
    case 'get_event_email_template':
        $obj        = new EventEmailTemplate();
        $count      = $obj->get_count();
        break;
    case 'get_careers':
        $obj        = new Career();
        $count      = $obj->get_count();
        break;
    case 'get_promotions':
        $obj        = new Promotion();
        $count      = $obj->get_count();
        break;
    case 'get_grade_models':
        $obj        = new GradeModel();
        $count      = $obj->get_count();
        break;
    case 'get_usergroups':
        $obj        = new UserGroup();
        $count      = $obj->get_count();
        break;
    case 'get_usergroups_teacher':
        $obj        = new UserGroup();
        $type = isset($_REQUEST['type']) ? $_REQUEST['type'] : 'registered';
        $course_id = api_get_course_int_id();
        if ($type == 'registered') {
            $count = $obj->get_usergroup_by_course_with_data_count($course_id);
        } else {
            $count = $obj->get_count();
        }
        break;
    default:
        exit;
}

//3. Calculating first, end, etc
$total_pages = 0;
if ($count > 0) {
    if (!empty($limit)) {
        $total_pages = ceil($count/$limit);
    }
}
if ($page > $total_pages) {
    $page = $total_pages;
}

$start = $limit * $page - $limit;
if ($start < 0) {
    $start = 0;
}

//4. Deleting an element if the user wants to
if (isset($_REQUEST['oper']) && $_REQUEST['oper'] == 'del') {
    $obj->delete($_REQUEST['id']);
}

$is_allowedToEdit = api_is_allowed_to_edit(null, true) || api_is_allowed_to_edit(true) || api_is_drh();

//5. Querying the DB for the elements
$columns = array();

switch ($action) {
    case 'get_course_exercise_medias':
        $columns = array('question');
        $result = Question::get_course_medias($course_id, $start, $limit, $sidx, $sord, $where_condition);
        break;
    case 'get_user_course_report_resumed':
        $columns = array(
            'extra_ruc',
            'training_hours',
            'count_users',
            'count_users_registered',
            'average_hours_per_user',
            'count_certificates'
        );

        $column_names = array(
            get_lang('Company'),
            get_lang('TrainingHoursAccumulated'),
            get_lang('CountOfSubscriptions'),
            get_lang('CountOfUsers'),
            get_lang('AverageHoursPerStudent'),
            get_lang('CountCertificates')
        );

        $extra_fields = UserManager::get_extra_fields(0, 100, null, null, true, true);

        if (!empty($extra_fields)) {
            foreach ($extra_fields as $extra) {
                if ($extra['1'] == 'ruc') {
                    continue;
                }
                $columns[] = $extra['1'];
                $column_names[] = $extra['3'];
            }
        }

        $result = CourseManager::get_user_list_from_course_code(
            null,
            null,
            "LIMIT $start, $limit",
            " $sidx $sord",
            null,
            null,
            true,
            true,
            array('ruc'),
            $courseCodeList,
            $userIdList
        );

        $new_result = array();
        if (!empty($result)) {
            foreach ($result as $row) {
                $row['training_hours'] = api_time_to_hms($row['training_hours']);
                $row['average_hours_per_user'] = api_time_to_hms($row['average_hours_per_user']);
                $new_result[] = $row;
            }
            $result = $new_result;
        }
        break;
    case 'get_user_course_report':
        $columns = array('course', 'user', 'time', 'certificate', 'progress_100', 'progress');
        $column_names = array(
            get_lang('Course'),
            get_lang('User'),
            get_lang('ManHours'),
            get_lang('CertificateGenerated'),
            get_lang('Approved'),
            get_lang('CourseAdvance')
        );

        $extra_fields = UserManager::get_extra_fields(0, 100, null, null, true, true);
        if (!empty($extra_fields)) {
            foreach ($extra_fields as $extra) {
                $columns[] = $extra['1'];
                $column_names[] = $extra['3'];
            }
        }

        $result = CourseManager::get_user_list_from_course_code(
            null,
            null,
            "LIMIT $start, $limit",
            " $sidx $sord",
            null,
            null,
            true,
            false,
            null,
            $courseCodeList,
            $userIdList
        );

        break;
	case 'get_user_skill_ranking':
        $columns = array('photo', 'firstname', 'lastname', 'skills_acquired', 'currently_learning', 'rank');
        $result = $skill->get_user_list_skill_ranking($start, $limit, $sidx, $sord, $where_condition);
        $result = msort($result, 'skills_acquired', 'asc');

        $skills_in_course = array();
        if (!empty($result)) {
            foreach ($result as &$item) {
                $user_info = api_get_user_info($item['user_id']);
                $personal_course_list = UserManager::get_personal_session_course_list($item['user_id']);
                $count_skill_by_course = array();
                foreach ($personal_course_list as $course_item) {
                    if (!isset($skills_in_course[$course_item['code']])) {
                        $count_skill_by_course[$course_item['code']] = $skill->get_count_skills_by_course($course_item['code']);
                        $skills_in_course[$course_item['code']] = $count_skill_by_course[$course_item['code']];
                    } else {
                        $count_skill_by_course[$course_item['code']] = $skills_in_course[$course_item['code']];
                    }
                }
                $item['photo'] = Display::img($user_info['avatar_small']);
                $item['currently_learning'] = !empty($count_skill_by_course) ? array_sum($count_skill_by_course) : 0;
            }
        }
        break;
    case 'get_work_teacher':
        $columns = array('type', 'title', 'sent_date', 'expires_on', 'ends_on', 'actions');
        $result = getWorkListTeacher($start, $limit, $sidx, $sord, $where_condition);
        break;
    case 'get_work_student':
        $columns = array('type', 'title', 'expires_on', 'others', 'actions');
        $result = getWorkListStudent($start, $limit, $sidx, $sord, $where_condition);
        break;
    case 'get_work_user_list_all':
        if (isset($_GET['type'])  && $_GET['type'] == 'simple') {
            $columns = array(
                'type', 'firstname', 'lastname', 'title', 'qualification', 'sent_date', 'qualificator_id', 'actions'
            );
        } else {
            $columns = array('type', 'firstname', 'lastname', 'title', 'sent_date', 'actions');
        }
        $result = get_work_user_list($start, $limit, $sidx, $sord, $work_id, $where_condition);
        break;
    case 'get_work_user_list_others':
        if (isset($_GET['type'])  && $_GET['type'] == 'simple') {
            $columns = array(
                'type', 'firstname', 'lastname',  'title', 'qualification', 'sent_date', 'qualificator_id', 'actions'
            );
        } else {
            $columns = array('type', 'firstname', 'lastname',  'title', 'sent_date', 'actions');
        }
        $where_condition .= " AND u.user_id <> ".api_get_user_id();
        $result = get_work_user_list($start, $limit, $sidx, $sord, $work_id, $where_condition);
        break;
    case 'get_work_user_list':
        if (isset($_GET['type'])  && $_GET['type'] == 'simple') {
            $columns = array(
                'type', 'title', 'qualification', 'sent_date', 'qualificator_id', 'actions'
            );
        } else {
            $columns = array('type', 'title', 'sent_date', 'actions');
        }

        $documents = getAllDocumentToWork($work_id, api_get_course_int_id());

        if (empty($documents)) {
            $where_condition .= " AND u.user_id = ".api_get_user_id();
            $result = get_work_user_list($start, $limit, $sidx, $sord, $work_id, $where_condition);
        } else {
            $result = get_work_user_list_from_documents(
                $start,
                $limit,
                $sidx,
                $sord,
                $work_id,
                api_get_user_id(),
                $where_condition
            );
        }
        break;
    case 'get_exercise_results':
        $course = api_get_course_info();
        // Used inside get_exam_results_data()
        $documentPath = api_get_path(SYS_COURSE_PATH) . $course['path'] . "/document";
        if ($is_allowedToEdit) {
            $columns = array(
                'firstname', 'lastname', 'username', 'group_name', 'exe_duration', 'start_date', 'exe_date', 'score', 'status', 'lp', 'actions'
            );
        }
        $result = get_exam_results_data($start, $limit, $sidx, $sord, $exercise_id, $where_condition);
		break;
	case 'get_hotpotatoes_exercise_results':
		$course = api_get_course_info();
		$documentPath = api_get_path(SYS_COURSE_PATH) . $course['path'] . "/document";
		$columns = array('firstname', 'lastname', 'username', 'group_name', 'exe_date',  'score', 'actions');
		$result = get_exam_results_hotpotatoes_data($start, $limit, $sidx, $sord, $hotpot_path, $where_condition); //get_exam_results_data($start, $limit, $sidx, $sord, $exercise_id, $where_condition);
		break;
    case 'get_sessions_tracking':
        if (api_is_drh()) {
            $sessions = SessionManager::get_sessions_followed_by_drh(api_get_user_id(), $start, $limit);
        } else {
            // Sessions for the coach
            $sessions = Tracking::get_sessions_coached_by_user(api_get_user_id(), $start, $limit);
        }

        $columns =  array(
            'name',
            'date',
            'course_per_session',
            'student_per_session',
            'details'
        );

        $result = array();
        if (!empty($sessions)) {
            foreach ($sessions as $session) {
                if (api_drh_can_access_all_session_content()) {
                    $count_courses_in_session = count(SessionManager::get_course_list_by_session_id($session['id']));
                } else {
                    $count_courses_in_session = count(Tracking::get_courses_followed_by_coach($user_id, $session['id']));
                }

                $count_users_in_session = count(SessionManager::get_users_by_session($session['id'], 0));
                $session_date = array();
                if (!empty($session['date_start']) && $session['date_start'] != '0000-00-00') {
                    $session_date[] = get_lang('From').' '.api_format_date($session['date_start'], DATE_FORMAT_SHORT);
                }

                if (!empty($session['date_end']) && $session['date_end'] != '0000-00-00') {
                    $session_date[] = get_lang('Until').' '.api_format_date($session['date_end'], DATE_FORMAT_SHORT);
                }

                if (empty($session_date)) {
                    $session_date_string = '-';
                } else {
                    $session_date_string = implode(' ', $session_date);
                }
                $sessionUrl = api_get_path(WEB_CODE_PATH).'mySpace/index.php?session_id='.$session['id'];
                $result[] = array(
                    'name' => $session['name'],
                    'date' => $session_date_string,
                    'course_per_session' => $count_courses_in_session,
                    'student_per_session' => $count_users_in_session,
                    'details' => Display::url(Display::return_icon('2rightarrow.gif'), $sessionUrl)
                );
            }
        }
        break;
    case 'get_sessions':
        $columns = array(
            'name', 'nbr_courses', 'nbr_users', 'category_name', 'date_start','date_end', 'coach_name', 'session_active', 'visibility'
        );

        $result = SessionManager::get_sessions_admin(
            array(
                'where' => $where_condition,
                'order' => "$sidx $sord",
                'limit'=> "$start , $limit"
            )
        );
        break;
    case 'get_exercise_progress':
        $sessionId = 0;
        if (!empty($_GET['course_id']) && !empty($_GET['exercise_id']))
        {
            $sessionId  = intval($_GET['session_id']);
            $courseId   = intval($_GET['course_id']);
            $exerciseId = intval($_GET['exercise_id']);
            $answer     = intval($_GET['answer']);
        }

        $columns = array(
            'session',
            'exercise_id',
            'quiz_title',
            'username',
            'lastname',
            'firstname',
            'time',
            'question_id',
            'question',
            'answer',
            'correct'
        );

        $result = Tracking::get_exercise_progress($sessionId, $courseId, $exerciseId, $answer,
            array(
                'where' => $where_condition,
                'order' => "$sidx $sord",
                'limit'=> "$start , $limit"
            )
        );
        break;
    case 'get_session_lp_progress':
        $sessionId = 0;
        if (!empty($_GET['course_id']))
        {
            $sessionId = intval($_GET['session_id']);
            $courseId = intval($_GET['course_id']);
            $course = api_get_course_info_by_id($courseId);
        }
        /**
         * Add lessons of course
         *
         */
        $columns = array(
            'username',
            'firstname',
            'lastname',
        );
        require_once api_get_path(SYS_CODE_PATH).'newscorm/learnpathList.class.php';
        $lessons = LearnpathList::get_course_lessons($course['code'], $sessionId);
        foreach ($lessons as $lesson_id => $lesson)
        {
            $columns[] = $lesson_id;
        }
        $columns[] = 'total';

        $result = SessionManager::get_session_lp_progress($sessionId, $courseId,
            array(
                'where' => $where_condition,
                'order' => "$sidx $sord",
                'limit'=> "$start , $limit"
            )
        );
        break;
    case 'get_survey_overview':
        $sessionId = 0;
        if (!empty($_GET['course_id']) && !empty($_GET['survey_id']))
        {
            $sessionId = intval($_GET['session_id']);
            $courseId  = intval($_GET['course_id']);
            $surveyId  = intval($_GET['survey_id']);
            //$course    = api_get_course_info_by_id($courseId);
        }
        /**
         * Add lessons of course
         */
        $columns = array(
            'username',
            'firstname',
            'lastname',
        );

        $questions = survey_manager::get_questions($surveyId, $courseId);

        foreach ($questions as $question_id => $question)
        {
            $columns[] = $question_id;
        }

        $result = SessionManager::get_survey_overview($sessionId, $courseId, $surveyId,
            array(
                'where' => $where_condition,
                'order' => "$sidx $sord",
                'limit'=> "$start , $limit"
            )
        );
        break;
    case 'get_session_progress':
        $columns = array(
            'lastname',
            'firstname',
            'username',
            #'profile',
            'total',
            'courses',
            'lessons',
            'exercises',
            'forums',
            'homeworks',
            'wikis',
            'surveys',
            //exercises
            'lessons_total' ,
            'lessons_done' ,
            'lessons_left' ,
            'lessons_progress',
            //exercises
            'exercises_total' ,
            'exercises_done' ,
            'exercises_left' ,
            'exercises_progress' ,
            //forums
            'forums_total' ,
            'forums_done' ,
            'forums_left' ,
            'forums_progress' ,
            //assignments
            'assignments_total' ,
            'assignments_done' ,
            'assignments_left' ,
            'assignments_progress' ,
            //Wiki
            'wiki_total',
            'wiki_revisions',
            'wiki_read',
            'wiki_unread',
            'wiki_progress',
            //surveys
            'surveys_total' ,
            'surveys_done' ,
            'surveys_left' ,
            'surveys_progress' ,
            );
        $sessionId = 0;
        if (!empty($_GET['course_id']))
        {
            $sessionId = intval($_GET['session_id']);
            $courseId = intval($_GET['course_id']);
        }
        $result = SessionManager::get_session_progress($sessionId, $courseId,
            array(
                'where' => $where_condition,
                'order' => "$sidx $sord",
                'limit'=> "$start , $limit"
            )
        );
        break;
    case 'get_session_access_overview':
        $columns = array(
            'logindate',
            'username',
            'lastname',
            'firstname',
            'clicks',
            'ip',
            'timeLoggedIn',
        );
        $sessionId = 0;
        if (!empty($_GET['course_id']))
        {
            $sessionId  = intval($_GET['session_id']);
            $courseId   = intval($_GET['course_id']);
            $studentId  = intval($_GET['student_id']);
            $profile    = intval($_GET['profile']);
            $date_from  = intval($_GET['date_from']);
            $date_to    = intval($_GET['date_to']);
        }

        $result = SessionManager::get_user_data_access_tracking_overview(intval($sessionId), intval($courseId), intval($studentId), intval($profile), $date_to, $date_from,
            array(
                'where' => $where_condition,
                'order' => "$sidx $sord",
                'limit'=> "$start , $limit"
            )
        );
        break;
    case 'get_timelines':
        $columns = array('headline', 'actions');

        if (!in_array($sidx, $columns)) {
            $sidx = 'headline';
        }
        $course_id = api_get_course_int_id();
        $result = Database::select(
            '*',
            $obj->table,
            array('where' => array('parent_id = ? AND c_id = ?' => array('0', $course_id)), 'order'=>"$sidx $sord", 'LIMIT'=> "$start , $limit")
        );
        $new_result = array();
        foreach ($result as $item) {
            if (!$item['status']) {
                $item['name'] = '<font style="color:#AAA">'.$item['name'].'</font>';
            }
            $item['headline'] = Display::url($item['headline'], api_get_path(WEB_CODE_PATH).'timeline/view.php?id='.$item['id']);
            $item['actions'] = Display::url(Display::return_icon('add.png', get_lang('AddItems')), api_get_path(WEB_CODE_PATH).'timeline/?action=add_item&parent_id='.$item['id']);
            $item['actions'] .= Display::url(Display::return_icon('edit.png', get_lang('Edit')), api_get_path(WEB_CODE_PATH).'timeline/?action=edit&id='.$item['id']);
            $item['actions'] .= Display::url(Display::return_icon('delete.png', get_lang('Delete')), api_get_path(WEB_CODE_PATH).'timeline/?action=delete&id='.$item['id']);

            $new_result[] = $item;
        }
        $result = $new_result;
        break;
    case 'get_gradebooks':
        $columns = array('name', 'certificates','skills', 'actions', 'has_certificates');
        if (!in_array($sidx, $columns)) {
            $sidx = 'name';
        }
        $result     = Database::select('*', $obj->table, array('order'=>"$sidx $sord", 'LIMIT'=> "$start , $limit"));
        $new_result = array();
        foreach ($result as $item) {
            if ($item['parent_id'] != 0) {
                continue;
            }
            $skills = $obj->get_skills_by_gradebook($item['id']);

            //Fixes bug when gradebook doesn't have names
            if (empty($item['name'])) {
                $item['name'] = $item['course_code'];
            }

            $item['name'] = Display::url($item['name'], api_get_path(WEB_CODE_PATH).'gradebook/index.php?id_session=0&cidReq='.$item['course_code']);

            if (!empty($item['certif_min_score']) && !empty($item['document_id'])) {
                $item['certificates'] = Display::return_icon('accept.png', get_lang('WithCertificate'), array(), ICON_SIZE_SMALL);
                 $item['has_certificates'] = '1';
            } else {
                $item['certificates'] = Display::return_icon('warning.png', get_lang('NoCertificate'), array(), ICON_SIZE_SMALL);
                $item['has_certificates'] = '0';
            }
            if (!empty($skills)) {
                foreach($skills as $skill) {
                    $item['skills'] .= Display::span($skill['name'], array('class' => 'label_tag skill'));
                }
            }
            $new_result[] = $item;
        }
        $result = $new_result;
        break;
    case 'get_event_email_template':
        $columns = array('subject', 'event_type_name', 'language_id', 'activated', 'actions');
        if (!in_array($sidx, $columns)) {
            $sidx = 'subject';
        }
        $result     = Database::select('*', $obj->table, array('order'=>"$sidx $sord", 'LIMIT'=> "$start , $limit"));
        $new_result = array();
        foreach ($result as $item) {
            $language_info = api_get_language_info($item['language_id']);
            $item['language_id'] = $language_info['english_name'];
            $item['actions'] = Display::url(Display::return_icon('edit.png', get_lang('Edit')), api_get_path(WEB_CODE_PATH).'admin/event_type.php?action=edit&event_type_name='.$item['event_type_name']);
            $item['actions'] .= Display::url(Display::return_icon('delete.png', get_lang('Delete')), api_get_path(WEB_CODE_PATH).'admin/event_controller.php?action=delete&id='.$item['id']);
            $new_result[] = $item;
        }
        $result = $new_result;
        break;
    case 'get_careers':
        $columns = array('name', 'description', 'actions');
        if (!in_array($sidx, $columns)) {
            $sidx = 'name';
        }
        $result     = Database::select('*', $obj->table, array('order'=>"$sidx $sord", 'LIMIT'=> "$start , $limit"));
        $new_result = array();
        foreach ($result as $item) {
            if (!$item['status']) {
                $item['name'] = '<font style="color:#AAA">'.$item['name'].'</font>';
            }
            $new_result[] = $item;
        }
        $result = $new_result;
        break;
    case 'get_promotions':
        $columns = array('name', 'career', 'description', 'actions');
        if (!in_array($sidx, $columns)) {
            $sidx = 'name';
        }

        $result = Database::select(
            'p.id,p.name, p.description, c.name as career, p.status',
            "$obj->table p LEFT JOIN ".Database::get_main_table(TABLE_CAREER)." c  ON c.id = p.career_id ",
            array('order' => "$sidx $sord", 'LIMIT'=> "$start , $limit")
        );

        $new_result = array();
        foreach ($result as $item) {
            if (!$item['status']) {
                $item['name'] = '<font style="color:#AAA">'.$item['name'].'</font>';
            }
            $new_result[] = $item;
        }
        $result = $new_result;
        break;
    case 'get_grade_models':
        $columns = array('name', 'description', 'actions');
        if (!in_array($sidx, $columns)) {
            $sidx = 'name';
        }
        $result     = Database::select('*', "$obj->table ", array('order' =>"$sidx $sord", 'LIMIT'=> "$start , $limit"));
        $new_result = array();
        foreach ($result as $item) {
            $new_result[] = $item;
        }
        $result = $new_result;
        break;
    case 'get_usergroups':
        $columns = array('name', 'users', 'courses','sessions','actions');
        $result = $obj->getUsergroupsPagination($sidx, $sord, $start, $limit);
        break;
    case 'get_extra_fields':
        $obj = new ExtraField($type);
        $columns = array('field_display_text', 'field_variable', 'field_type', 'field_changeable', 'field_visible', 'field_filter', 'field_order');
        $result  = Database::select('*', $obj->table, array('order'=>"$sidx $sord", 'LIMIT'=> "$start , $limit"));
        $new_result = array();
        if (!empty($result)) {
            foreach ($result as $item) {
                $item['field_type']         = $obj->get_field_type_by_id($item['field_type']);
                $item['field_changeable']   = $item['field_changeable'] ? Display::return_icon('right.gif') : Display::return_icon('wrong.gif');
                $item['field_visible']      = $item['field_visible'] ? Display::return_icon('right.gif') : Display::return_icon('wrong.gif');
                $item['field_filter']       = $item['field_filter'] ? Display::return_icon('right.gif') : Display::return_icon('wrong.gif');
                $new_result[]        = $item;
            }
            $result = $new_result;
        }
        break;
    case 'get_extra_field_options':
        $obj = new ExtraFieldOption($type);
        $columns = array('option_display_text', 'option_value', 'option_order');
        $result  = Database::select('*', $obj->table, array('where' => array("field_id = ? " => $field_id),'order'=>"$sidx $sord", 'LIMIT'=> "$start , $limit"));
        break;
    case 'get_usergroups_teacher':
        $columns = array('name', 'users', 'actions');
        $options = array('order'=>"name $sord", 'LIMIT'=> "$start , $limit");
        $options['course_id'] = $course_id;
        switch ($type) {
            case 'not_registered':
                $options['where'] = array(" (course_id IS NULL OR course_id != ?) " => $course_id);
                $result = $obj->get_usergroup_not_in_course($options);
                break;
            case 'registered':
                $options['where'] = array(" usergroup.course_id = ? " =>  $course_id);
                $result = $obj->get_usergroup_in_course($options);
                break;
        }
        $new_result = array();
        if (!empty($result)) {
            foreach ($result as $group) {
                $group['users'] = count($obj->get_users_by_usergroup($group['id']));
                if ($obj->usergroup_was_added_in_course($group['id'], $course_id)) {
                    $url  = 'class.php?action=remove_class_from_course&id='.$group['id'];
                    $icon = Display::return_icon('delete.png', get_lang('Remove'));
                } else {
                    $url  = 'class.php?action=add_class_to_course&id='.$group['id'];
                    $icon = Display::return_icon('add.png', get_lang('Add'));
                }
                $group['actions']    = Display::url($icon, $url);
                $new_result[]        = $group;
            }
            $result = $new_result;
        }

        if (!in_array($sidx, $columns)) {
            $sidx = 'name';
        }
        //Multidimensional sort
        $result = msort($result, $sidx, $sord);
        break;
    default:
        exit;
}

$allowed_actions = array(
    'get_careers',
    'get_promotions',
    'get_usergroups',
    'get_usergroups_teacher',
    'get_gradebooks',
    'get_sessions',
    'get_session_access_overview',
    'get_sessions_tracking',
    'get_session_lp_progress',
    'get_survey_overview',
    'get_session_progress',
    'get_exercise_progress',
    'get_exercise_results',
    'get_hotpotatoes_exercise_results',
    'get_work_teacher',
    'get_work_student',
    'get_work_user_list',
    'get_work_user_list_others',
    'get_work_user_list_all',
    'get_timelines',
    'get_grade_models',
    'get_event_email_template',
    'get_user_skill_ranking',
    //'get_extra_fields',
    //'get_extra_field_options',
    //'get_course_exercise_medias',
    'get_user_course_report',
    'get_user_course_report_resumed'
);

//5. Creating an obj to return a json
if (in_array($action, $allowed_actions)) {
    $response           = new stdClass();
    $response->page     = $page;
    $response->total    = $total_pages;
    $response->records  = $count;

    if ($operation && $operation == 'excel') {
        $j = 1;
        require_once api_get_path(LIBRARY_PATH).'export.lib.inc.php';

        $array = array();
        if (empty($column_names)) {
            $column_names = $columns;
        }

        //Headers
        foreach ($column_names as $col) {
            $array[0][] = $col;
        }
        foreach ($result as $row) {
            foreach ($columns as $col) {
                $array[$j][] = strip_tags($row[$col]);
            }
            $j++;
        }
        switch ($export_format) {
            case 'xls':
                //TODO add date if exists
                $file_name = (!empty($action)) ? $action : 'company_report';
                require_once api_get_path(LIBRARY_PATH).'browser/Browser.php';
                $browser = new Browser();
                if ($browser->getPlatform() == Browser::PLATFORM_WINDOWS) {
                    Export::export_table_xls($array, $file_name, 'ISO-8859-15');
                } else {
                    Export::export_table_xls($array, $file_name);
                }
                break;
            case 'csv':
            default:
                //TODO add date if exists
                $file_name = (!empty($action)) ? $action : 'company_report'; 
                Export::export_table_csv($array, $file_name);
                break;
        }
        exit;
    }

    $i = 0;

    if (!empty($result)) {
        foreach ($result as $row) {
            //print_r($row);
            // if results tab give not id, set id to $i otherwise id="null" for all <tr> of the jqgrid - ref #4235
            if ($row['id'] == "") {
                $response->rows[$i]['id']=$i;
            } else {
                $response->rows[$i]['id']=$row['id'];
            }
            $array = array();
            foreach ($columns as $col) {
                $array[] = isset($row[$col]) ? $row[$col] : null;
            }
            $response->rows[$i]['cell']=$array;
            $i++;
        }
    }
    echo json_encode($response);
}
exit;
