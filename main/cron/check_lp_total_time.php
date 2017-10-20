<?php
/* For licensing terms, see /license.txt */

/**
 * This script checks and propose a query fix for LP items with high time values
 * Only if the total LP time is bigger than the total course time.
 **/

exit;

require_once __DIR__.'/../../main/inc/global.inc.php';

api_protect_admin_script();
$max = 10;
$counter = 0;
// Check Sessions
$sessions = SessionManager::get_sessions_admin();
foreach($sessions as $session) {
    $sessionId = $session['id'];
    $courses = SessionManager::getCoursesInSession($sessionId);

    foreach($courses as $courseId) {
        $courseInfo = api_get_course_info_by_id($courseId);
        $courseCode = $courseInfo['code'];
        $users = CourseManager::get_user_list_from_course_code(
            $courseCode,
            $sessionId,
            null,
            null,
            0
        );

        foreach ($users as $user) {
            $userId = $user['user_id'];
            $result = compareLpTimeAndCourseTime($userId, $courseInfo, $sessionId);
            if ($result) {
                $counter++;
            }

            if ($counter > $max) {
                //break 3;
            }
        }
    }
}

// Courses
/*$courses = CourseManager::get_courses_list();
foreach($courses as $courseInfo) {
    $courseCode = $courseInfo['code'];
    $courseInfo['real_id'] = $courseInfo['id'];
    $users = CourseManager::get_user_list_from_course_code($courseCode);
    foreach ($users as $user) {
        $userId = $user['id'];
        compareLpTimeAndCourseTime($userId, $courseInfo);
    }
}*/

/**
 * @param int $userId
 * @param array $courseInfo
 * @param int $sessionId
 * @return string
 */
function compareLpTimeAndCourseTime($userId, $courseInfo, $sessionId = 0)
{
    $defaultValue = 3600; // 1 hour
    $courseCode = $courseInfo['code'];
    $courseId = $courseInfo['real_id'];

    $totalLpTime = Tracking::get_time_spent_in_lp(
        $userId,
        $courseCode,
        array(),
        $sessionId
    );

    if (empty($totalLpTime)) {
        return false;
    }

    $totalCourseTime = Tracking::get_time_spent_on_the_course(
        $userId,
        $courseId,
        $sessionId
    );
    $content = '';
    if ($totalLpTime > $totalCourseTime) {
        $totalCourseTime = api_time_to_hms($totalCourseTime);
        $totalLpTime = api_time_to_hms($totalLpTime);

        $content = "Total course: $totalCourseTime / Total LP: $totalLpTime";
        $url = api_get_path(WEB_CODE_PATH).'mySpace/myStudents.php?student='.$userId.'&course='.$courseCode.'&id_session='.$sessionId;
        $content .= Display::url('Check', $url, ['target' => '_blank']);
        $content .= PHP_EOL;


        // Check posible records with high values
        $sql = "SELECT iv.iid, lp_id, total_time FROM c_lp_view v 
                INNER JOIN c_lp_item_view iv
                ON (iv.c_id = v.c_id AND v.id = iv.lp_view_id)
                WHERE 
                    user_id = $userId AND 
                    v.c_id = $courseId AND 
                    session_id = $sessionId
                ORDER BY total_time desc
                LIMIT 10
                ";
        $result = Database::query($sql);
        $results = Database::store_result($result, 'ASSOC');
        if (!empty($results)) {
            $content .= 'Top 10 high lp item times'.PHP_EOL.PHP_EOL;
            foreach ($results as $item) {
                $lpId = $item['lp_id'];
                $link = api_get_path(WEB_CODE_PATH).'mySpace/lp_tracking.php?cidReq='.$courseCode.'&course='.$courseCode.'&origin=&lp_id='.$lpId.'&student_id='.$userId.'&id_session='.$sessionId;

                $content .= "total_time = ".api_time_to_hms($item['total_time']).PHP_EOL;
                $content .= Display::url('See report before update', $link, ['target' => '_blank']).PHP_EOL;
                $content .= "SQL with possible fix:".PHP_EOL;
                $content .= "UPDATE c_lp_item_view SET total_time = '$defaultValue' WHERE iid = ".$item['iid'].";".PHP_EOL.PHP_EOL;
            }
        }
    }

    echo nl2br($content);

    return true;
}

exit;