<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Booking_request extends MY_Controller
{
	function __construct()
	{

		parent::__construct(array('stu', 'emp', 'hod', 'hos', 'est_ar', 'dsw', 'est_da4'));

		$this->addJS("sah_booking/booking.js");

		$this->load->model ('sah_booking/sah_booking_model');
		$this->load->model('sah_booking/sah_allotment_model');
		$this->load->model('user_model');
	}

	function auth_is($auth)
	{
		foreach($this->session->userdata('auth') as $a){
			if($a == $auth)
				return;
		}
		$this->session->set_flashdata('flashWarning', 'You do not have access to that page!');
		redirect('home');
	}

	function get_head($dept_id)
	{
		//get the hod or hos of dept
		//if dept is academic then hod otherwise hos for nonacademic
		if($this->sah_booking_model->is_academic($dept_id))	//returns true if academic dept
			return 'hod';
		else return 'hos';
	}

	//this function retrieves all the pending, approved, rejected and new applications for the corresponding auth
	function app_list($auth)
	{
		$this->auth_is($auth);

		if($auth == 'dsw' || $auth == 'est_ar')
			$dept_id = 'all';
		else $dept_id = $this->session->userdata('dept_id');

		$res = $this->sah_booking_model->get_requests ("Pending", $auth, $dept_id);
		$total_rows_pending = count($res);
		$data_array_pending = array();
		$sno = 1;
		foreach ($res as $row)
		{
			$data_array_pending[$sno]=array();
			$j=1;
			$data_array_pending[$sno][$j++] = $row['app_num'];
			$data_array_pending[$sno][$j++] = date('j M Y g:i A', strtotime($row['app_date']));
			$data_array_pending[$sno][$j++] = $this->user_model->getNameById($row['user_id']);
			$data_array_pending[$sno][$j++] = $row['no_of_guests'];
			$sno++;
		}

		if($auth == 'hod' || $auth == 'hos' || $auth == 'dsw' || $auth == 'est_ar')
		{
			$res = $this->sah_booking_model->get_requests ("Cancel", $auth, $dept_id);
			$total_rows_cancel = count($res);
			$data_array_cancel = array();
			$sno = 1;
			foreach ($res as $row)
			{
				$data_array_cancel[$sno]=array();
				$j=1;
				$data_array_cancel[$sno][$j++] = $row['app_num'];
				$data_array_cancel[$sno][$j++] = date('j M Y g:i A', strtotime($row['app_date']));
				$data_array_cancel[$sno][$j++] = $this->user_model->getNameById($row['user_id']);
				$data_array_cancel[$sno][$j++] = $row['no_of_guests'];
				$data_array_cancel[$sno][$j++] = $row['deny_reason'];
				$sno++;
			}
		}

		$res = $this->sah_booking_model->get_requests ("Approved", $auth, $dept_id);
		$total_rows_approved = count($res);
		$data_array_approved = array();
		$sno = 1;
		foreach ($res as $row)
		{
			$data_array_approved[$sno]=array();
			$j=1;
			$data_array_approved[$sno][$j++] = $row['app_num'];
			$data_array_approved[$sno][$j++] = date('j M Y g:i A', strtotime($row['app_date']));
			$data_array_approved[$sno][$j++] = $this->user_model->getNameById($row['user_id']);
			$data_array_approved[$sno][$j++] = $row['no_of_guests'];
			$data_array_approved[$sno]['est_ar_status'] = $row['est_ar_status'];
			$data_array_approved[$sno]['guest_checked_in'] = count($this->sah_booking_model->get_guest_details($row['app_num']));
			$sno++;
		}

		$res = $this->sah_booking_model->get_requests ("Rejected", $auth, $dept_id);	//in case of est_da4, there won't be any rejected apps
		$total_rows_rejected = count($res);
		$data_array_rejected = array();
		$sno = 1;
		foreach ($res as $row)
		{
			$data_array_rejected[$sno]=array();
			$j=1;
			$data_array_rejected[$sno][$j++] = $row['app_num'];
			$data_array_rejected[$sno][$j++] = date('j M Y g:i A', strtotime($row['app_date']));
			$data_array_rejected[$sno][$j++] = $this->user_model->getNameById($row['user_id']);
			$data_array_rejected[$sno][$j++] = $row['no_of_guests'];
			$sno++;
		}

		$res = $this->sah_booking_model->get_new_applications ($auth, $dept_id); //function arguments: auth dept_id
		$total_new_apps = count($res);
		$data_array_new_apps = array();
		$sno = 1;
		foreach ($res as $row)
		{
			$data_array_new_apps[$sno]=array();
			$j=1;
			$data_array_new_apps[$sno][$j++] = $row['app_num'];
			$data_array_new_apps[$sno][$j++] = date('j M Y g:i A', strtotime($row['app_date']));
			$data_array_new_apps[$sno][$j++] = $this->user_model->getNameById($row['user_id']);
			$data_array_new_apps[$sno][$j++] = $row['no_of_guests'];
			$sno++;
		}

		$data = array(
			'data_array_pending' => $data_array_pending,
			'total_rows_pending' => $total_rows_pending,
			'data_array_cancel' => $data_array_cancel,
			'total_rows_cancel' => $total_rows_cancel,
			'data_array_approved' => $data_array_approved,
			'total_rows_approved' => $total_rows_approved,
			'data_array_rejected' => $data_array_rejected,
			'total_rows_rejected' => $total_rows_rejected,
			'data_array_new_apps' => $data_array_new_apps,
			'total_new_apps' => $total_new_apps,
			'auth' => $auth
		);

		$this->drawHeader('Senior Academic Hostel');
		$this->load->view('sah_booking/view_requests',$data);
		$this->drawFooter();
	}

	//this function retrieves all the pending, approved and new applications for est_da4
	function est_da4_app_list()
	{
		$this->auth_is('est_da4');
		$res = $this->sah_booking_model->get_requests ("Pending", 'est_da4', 'all');
		$total_rows_pending = count($res);
		$data_array_pending = array();
		$sno = 1;
		foreach ($res as $row)
		{
			$data_array_pending[$sno]=array();
			$j=1;
			$data_array_pending[$sno][$j++] = $row['app_num'];
			$data_array_pending[$sno][$j++] = date('j M Y g:i A', strtotime($row['app_date']));
			$data_array_pending[$sno][$j++] = $this->user_model->getNameById($row['user_id']);
			$data_array_pending[$sno][$j++] = $row['no_of_guests'];
			$sno++;
		}

		$res = $this->sah_booking_model->get_requests ("Approved", 'est_da4', 'all');
		$total_rows_approved = count($res);
		$data_array_approved = array();
		$sno = 1;
		foreach ($res as $row)
		{
			$data_array_approved[$sno]=array();
			$j=1;
			$data_array_approved[$sno][$j++] = $row['app_num'];
			$data_array_approved[$sno][$j++] = date('j M Y g:i A', strtotime($row['app_date']));
			$data_array_approved[$sno][$j++] = $this->user_model->getNameById($row['user_id']);
			$data_array_approved[$sno][$j++] = $row['no_of_guests'];
			$sno++;
		}
		$res = $this->sah_booking_model->get_new_applications ('est_da4', 'all'); //function arguments: auth dept_id
		$total_new_apps = count($res);
		$data_array_new_apps = array();
		$sno = 1;
		foreach ($res as $row)
		{
			$data_array_new_apps[$sno]=array();
			$j=1;
			$data_array_new_apps[$sno][$j++] = $row['app_num'];
			$data_array_new_apps[$sno][$j++] = date('j M Y g:i A', strtotime($row['app_date']));
			$data_array_new_apps[$sno][$j++] = $this->user_model->getNameById($row['user_id']);
			$data_array_new_apps[$sno][$j++] = $row['no_of_guests'];
			$sno++;
		}
		$data = array(
			'data_array_pending' => $data_array_pending,
			'total_rows_pending' => $total_rows_pending,
			'data_array_approved' => $data_array_approved,
			'total_rows_approved' => $total_rows_approved,
			'data_array_new_apps' => $data_array_new_apps,
			'total_new_apps' => $total_new_apps,
			'auth' => 'est_da4'
		);

		$this->drawHeader('Senior Academic Hostel');
		$this->load->view('sah_booking/est_da4_requests',$data);
		$this->drawFooter();
	}

	//this function displays the application details for respective auths
	//can be accessed by clicking on the application number link in app list
	function details ($app_num, $auth)
	{
		$this->auth_is($auth);
		$res = $this->sah_booking_model->get_booking_details($app_num);
		if(!count($res)) {
			$this->session->set_flashdata('flashError', 'Application '.$app_num.' is not valid!');
			redirect('home');
		}

		foreach($res as $row) //it will output only one element corresponding to app_num
		{
			$data = array(
				'user_id' => $row['user_id'],
				'app_num' => $row['app_num'],
				'app_date' => date('j M Y g:i A', strtotime($row['app_date'])),
				'user' => $this->user_model->getNameById($row['user_id']),
				'purpose' => $row['purpose'],
				'purpose_of_visit' => $row['purpose_of_visit'],
				'name' => $row['name'],
				'designation' => $row['designation'],
				'check_in' => $row['check_in'],
				'check_out' => $row['check_out'],
				'no_of_guests' => $row['no_of_guests'],
				'double_AC' => $row['double_AC'],
				'suite_AC' => $row['suite_AC'],
				'boarding_required' => $row['boarding_required'],
				'school_guest' => $row['school_guest'],
				'file_path' => $row['file_path'],

				'hod_status' => $row['hod_status'],
				'hod_action_timestamp' => $row['hod_action_timestamp'],
				'dsw_status' => $row['dsw_status'],
				'dsw_action_timestamp' => $row['dsw_action_timestamp'],
				'ctk_allotment_status' => $row['ctk_allotment_status'],
				'ctk_action_timestamp' => $row['ctk_action_timestamp'],
				'est_ar_status' => $row['est_ar_status'],
				'est_ar_action_timestamp' => $row['est_ar_action_timestamp'],
				'deny_reason' => $row['deny_reason']
			);
		}

		$dept = $this->user_model->getById($this->sah_booking_model->get_request_user_id($app_num))->dept_id; //returns the department of the applicant
		if($this->sah_booking_model->is_academic($dept))	//returns true if academic dept
				$academic = 'yes';
		else $academic = 'no';
		$data['academic'] = $academic;
		$data['auth'] = $auth;
		$data['dept'] = $dept;

		$allotted_rooms = $this->sah_allotment_model->get_allocated_room_details($app_num);
		$data['no_of_rooms'] = count($allotted_rooms);
		$sno = 0;
		foreach($allotted_rooms as $allotted_room)
			$data['rooms'][$sno++] = $this->sah_allotment_model->get_room_details($allotted_room['room_id']);

		//stu, emp, hod, hos, dsw, est_ar, est_da4 have same view
		//caretaker has different view for looking at details before allotting room
		//caretaker has different view for allotting room
 		$this->drawHeader ("Booking Details");
 		if($auth == 'est_da4' && $data['ctk_allotment_status'] == 'Pending')
			$this->load->view('sah_booking/est_da4',$data);
		else $this->load->view('sah_booking/booking_details',$data);
		$this->drawFooter();
	}

	//this function handles the notifications and passes control to details
	function notification_handler($app_num, $auth)
	{
		$this->auth_is($auth);
		$res = $this->sah_booking_model->get_booking_details($app_num);

		foreach($res as $row) //it will output only one element corresponding to app_num
		{
			$data = array(
				'hod_status' => $row['hod_status'],
				'hod_action_timestamp' => $row['hod_action_timestamp'],
				'dsw_status' => $row['dsw_status'],
				'dsw_action_timestamp' => $row['dsw_action_timestamp'],
				'ctk_allotment_status' => $row['ctk_allotment_status'],
				'ctk_allotment_timestamp' => $row['ctk_action_timestamp'],
				'est_ar_status' => $row['est_ar_status'],
				'est_ar_action_timestamp' => $row['est_ar_action_timestamp'],
				'deny_reason' => $row['deny_reason']
			);
		}
		if($data['est_ar_status'] == 'Cancelled' ||
			$data['hod_status'] == 'Cancel' ||
			$data['dsw_status'] == 'Cancel' ||
			$data['est_ar_status'] == 'Cancel')
		{
			$this->session->set_flashdata('flashSuccess', 'This request has been Cancelled by Applicant');
		}
		else if((($auth == 'hod' || $auth == 'hos') && $data['hod_status'] == 'Approved') ||
				($auth == 'dsw' && $data['dsw_status'] == 'Approved') ||
				($auth == 'est_da4' && $data['ctk_allotment_status'] == 'Approved') ||
				($auth == 'est_ar' && $data['est_ar_status'] == 'Approved'))
		{
			$this->session->set_flashdata('flashSuccess', 'This request has been already Approved');
		}
		else if((($auth == 'hod' || $auth == 'hos') && $data['hod_status'] == 'Rejected') ||
				($auth == 'dsw' && $data['dsw_status'] == 'Rejected') ||
				($auth == 'est_da4' && $data['ctk_allotment_status'] == 'Rejected') ||
				($auth == 'est_ar' && $data['est_ar_status'] == 'Rejected'))
		{
			$this->session->set_flashdata('flashSuccess', 'This request has been already Rejected');
		}
		redirect('sah_booking/booking_request/details/'.$app_num.'/'.$auth);
	}

	//this function updates the status of current actor and next actor
	//it also sends notifications to the next actor
	function official_action($app_num, $auth)
	{
		$this->auth_is($auth);
		$status = $this->input->post ('status');
		$reason = $this->input->post ('reason');

		if ($status == "Approved")
			$reason = "NULL";

		$b_detail = $this->sah_booking_model->get_booking_details($app_num);

		//official action after user requests cancellation
		if($b_detail[0]['hod_status'] === 'Cancel' ||
			$b_detail[0]['hod_status'] === 'Cancelled' ||
			$b_detail[0]['dsw_status'] === 'Cancel' ||
			$b_detail[0]['dsw_status'] === 'Cancelled' ||
			$b_detail[0]['est_ar_status'] === 'Cancelled') {
				$this->session->set_flashdata('flashError','Cannot complete action! Applicant has cancelled booking request.');
				redirect('sah_booking/booking_request/app_list/'.$auth);
		}

		$this->sah_booking_model->update_action($app_num, $auth, $status, $reason);

		$this_user = '';
		$to_id = ''; //this is the id to which notification is to be sent
		$to_auth = '';
		$to_msg = '';
		$to_msg_header = 'Approve/Reject Pending Request';
		$user_id = $this->sah_booking_model->get_request_user_id($app_num);
		$user_auth = '';

		//set user_auth
		if($this->user_model->getById($user_id)->auth_id == 'stu')
			$user_auth = 'stu';
		else $user_auth = 'emp';

		//set this_user
		switch($auth)
		{
			case 'hod': $this_user = 'Head of Department';
						break;
			case 'hos': $this_user = 'Head of Section';
						break;
			case 'dsw': $this_user = 'Dean of Students Welfare';
						break;
			case 'est_ar': $this_user = 'EST Assist. Registrar';
		}

		//set to_auth, to_msg;
		switch($auth)
		{
			case 'hod':
			case 'hos':
			case 'dsw': $to_auth = 'est_da4';
						$to_msg = 'Pending for Room Allotment';
						$to_header = 'SAH Room Allotment Request';
						break;
			case 'est_da4':
						$to_auth = 'est_ar';
						$to_msg = 'Pending for your Approval/Rejection';
						$to_header = 'SAH Room Booking Request';
						break;
			case 'est_ar':	$to_auth = $user_auth;
						$to_msg = 'Approved';
						$to_msg_header = 'SAH Booking Status';
		}

		//set to_id
		if($auth == 'est_ar')
			$to_id = $user_id;
		else {
			$res = $this->user_model->getUsersByDeptAuth('all', $to_auth); //get the users to whom approval/rejection requests are to be sent
			foreach($res as $row)
				$to_id = $row->id;
		}

		//set academic
		$dept = $this->user_model->getById($this->sah_booking_model->get_request_user_id($app_num))->dept_id; //returns the department of the applicant
		if($this->sah_booking_model->is_academic($dept))	//returns true if academic dept
			$academic = true;
		else
			$academic = false;
		$res = $this->user_model->getUsersByDeptAuth($dept, $this->get_head($dept)); //get the users to whom approval/rejection requests are to be sent
		foreach($res as $row)
			$hod_id = $row->id;
		$hod_auth = $this->get_head($dept);

		$res = $this->user_model->getUsersByDeptAuth('all', 'est_ar'); //get the users to whom approval/rejection requests are to be sent
		foreach($res as $row)
			$est_ar = $row->id;

		$res = $this->user_model->getUsersByDeptAuth('all', 'est_da4'); //get the users to whom approval/rejection requests are to be sent
		foreach($res as $row)
			$est_da4_id = $row->id;

		$res = $this->user_model->getUsersByDeptAuth('all', 'est_da5');
		foreach($res as $row)
			$est_da5_id = $row->id;

		$res = $this->user_model->getUsersByDeptAuth('all', 'dsw'); //get the users to whom approval/rejection requests are to be sent
		foreach($res as $row)
			$dsw_id = $row->id;

		if ($status == "Approved") {
			//verify the notification link
			$this->notification->notify ($to_id, $to_auth, $to_msg_header, "SAH Room Booking Request (Application No. : ".$app_num." ) is ".$to_msg.".", "sah_booking/booking_request/notification_handler/".$app_num."/".$to_auth, "");
			if($auth === 'est_ar')
				$this->notification->notify ($est_da5_id, 'est_da5', $to_msg_header, "SAH Room Booking Request (Application No. : ".$app_num." ) is ".$to_msg.".", "sah_booking/guest_details/edit/".$app_num, "");
		}
		else
		{
			//if current user is dsw|hod|hos, send notification to user only
			//if current user is est_ar, send notification to user, hod/hos, caretaker
			if($auth == 'hod' || $auth == 'hos' || $auth == 'dsw')
				$this->notification->notify ($user_id, $user_auth, "SAH Booking Status", "Your Request for SAH Room Allotment (Application No. : ".$app_num." ) has been Rejected by ".$this_user.".", "sah_booking/booking_request/details/".$app_num.'/'.$user_auth, "");
			else if($auth == 'est_ar')
			{
				$users = array(
					array(
						'id' => $user_id, 'auth' => $user_auth
					),
					array(
						'id' => $est_da4_id, 'auth' => 'est_da4'
					),
					/*array(
						'id' => $to_id, 'auth' => 'hod'
					),*/
					array(
						'id' => $hod_id, 'auth' => $hod_auth
					),
					array(
						'id' => $dsw_id, 'auth' => 'dsw'
					)
				);
				foreach($users as $user)
				{
					if(($user_auth == 'emp' && $user['auth'] == 'dsw') || ($user_auth == 'stu' && ($user['auth'] == 'hod' || $user['auth'] == 'hos')) || ($academic && $user['auth'] == 'hos')  || (!$academic && $user['auth'] == 'hod'))
						continue;
					$this->notification->notify ($user['id'], $user['auth'], "SAH Booking Status", "Your Request for SAH Room Allotment (Application No. : ".$app_num.") has been Rejected by ".$this_user.".", "sah_booking/booking_request/details/".$app_num."/".$user['auth'], "");
				}
			}
		}
		$this->session->set_flashdata('flashSuccess','Request has been successfully '.$status.'.');
		redirect('sah_booking/booking_request/app_list/'.$auth);
	}

	//this function handles notifications for cancellation
	function cancel($app_num, $auth)
	{
		$this->auth_is($auth);
		$res = $this->sah_booking_model->get_booking_details($app_num);

		foreach($res as $row) //it will output only one element corresponding to app_num
		{
			$data = array(
				'user_id' => $row['user_id'],
				'app_num' => $row['app_num'],
				'app_date' => date('j M Y g:i A', strtotime($row['app_date'])),
				'user' => $this->user_model->getNameById($row['user_id']),
				'purpose' => $row['purpose'],
				'purpose_of_visit' => $row['purpose_of_visit'],
				'name' => $row['name'],
				'designation' => $row['designation'],
				'check_in' => $row['check_in'],
				'check_out' => $row['check_out'],
				'no_of_guests' => $row['no_of_guests'],
				'double_AC' => $row['double_AC'],
				'suite_AC' => $row['suite_AC'],
				'boarding_required' => $row['boarding_required'],
				'school_guest' => $row['school_guest'],
				'file_path' => $row['file_path'],

				'hod_status' => $row['hod_status'],
				'hod_action_timestamp' => $row['hod_action_timestamp'],
				'dsw_status' => $row['dsw_status'],
				'dsw_action_timestamp' => $row['dsw_action_timestamp'],
				'ctk_allotment_status' => $row['ctk_allotment_status'],
				'ctk_action_timestamp' => $row['ctk_action_timestamp'],
				'est_ar_status' => $row['est_ar_status'],
				'est_ar_action_timestamp' => $row['est_ar_action_timestamp'],
				'deny_reason' => $row['deny_reason']
			);
		}

		$dept = $this->user_model->getById($this->sah_booking_model->get_request_user_id($app_num))->dept_id; //returns the department of the applicant
		if($this->sah_booking_model->is_academic($dept))	//returns true if academic dept
				$academic = 'yes';
		else $academic = 'no';
		$data['academic'] = $academic;
		$data['auth'] = $auth;

		$allotted_rooms = $this->sah_allotment_model->get_allocated_room_details($app_num);
		$data['no_of_rooms'] = count($allotted_rooms);
		$sno = 0;
		foreach($allotted_rooms as $allotted_room)
			$data['rooms'][$sno++] = $this->sah_allotment_model->get_room_details($allotted_room['room_id']);

		$this->drawHeader ("Booking Details");
		$this->load->view('sah_booking/booking_details',$data);
		$this->drawFooter();
	}

	function cancellation($app_num, $auth)
	{
		$this->auth_is($auth);
		$res = $this->sah_booking_model->get_booking_details($app_num);

		foreach($res as $row)
		{
			$data = array(
				'user_id' => $row['user_id'],
				'purpose' => $row['purpose'],
				'hod_status' => $row['hod_status'],
				'hod_action_timestamp' => $row['hod_action_timestamp'],
				'dsw_status' => $row['dsw_status'],
				'dsw_action_timestamp' => $row['dsw_action_timestamp'],
				'ctk_allotment_status' => $row['ctk_allotment_status'],
				'ctk_allotment_timestamp' => $row['ctk_action_timestamp'],
				'est_ar_status' => $row['est_ar_status'],
				'est_ar_action_timestamp' => $row['est_ar_action_timestamp'],
				'deny_reason' => $row['deny_reason']
			);
		}

		//cancellation after official rejection
		if($data['hod_status'] === 'Rejected' ||
			$data['dsw_status'] === 'Rejected' ||
			$data['est_ar_status'] === 'Rejected') {
				$this->session->set_flashdata('flashError', 'Error! Application has been rejected already.');
				if($auth === 'stu' || $auth === 'emp')
					redirect('sah_booking/booking/track_status');
				else redirect('sah_booking/booking_request/app_list/'.$auth);
		}

		//user cancellation after est_ar forced cancellation
		if(($auth === 'stu' || $auth === 'emp') && $data['est_ar_status'] === 'Cancelled') {
			$this->session->set_flashdata('flashError', 'Error! Application has been rejected already.');
			redirect('sah_booking/booking/track_status');
		}

		if($auth == 'stu' || $auth == 'emp' || $auth == 'est_ar')
		{
			$cancel_reason = $this->input->post('cancel_reason');
			if($cancel_reason)
				$this->sah_booking_model->set_cancel_reason($app_num, $cancel_reason);
		}

		$dept = $this->user_model->getById($this->sah_booking_model->get_request_user_id($app_num))->dept_id; //returns the department of the applicant
		$to_auth = $this->get_head($dept);

		$user_auth = $this->sah_booking_model->get_user_auth($app_num)['auth_id'];
		$res = $this->user_model->getUsersByDeptAuth($dept, $to_auth); //get the users to whom approval/rejection requests are to be sent
		foreach($res as $row)
			$to_id = $row->id;

		$res = $this->user_model->getUsersByDeptAuth('all', 'est_ar'); //get the users to whom approval/rejection requests are to be sent
		foreach($res as $row)
			$est_ar = $row->id;

		$res = $this->user_model->getUsersByDeptAuth('all', 'est_da4'); //get the users to whom approval/rejection requests are to be sent
		foreach($res as $row)
			$est_da4_id = $row->id;

		$res = $this->user_model->getUserdByDeptAuth('all', 'est_da5');
		foreach($res as $row)
			$est_da5_id = $row->id;

		$res = $this->user_model->getUsersByDeptAuth('all', 'dsw'); //get the users to whom approval/rejection requests are to be sent
		foreach($res as $row)
			$to_id = $row->id;

		//button will only be shown if request is not cancelled, ie. checkin date is not set to 1970
		//if so, when viewing the application details, it'll show cancelled by Applicant and the reason
		if($auth == 'emp') //applicant can communicate with hod/hos, dsw, ctk only (in case of emp personal)
		{
			//if the first recepient of notification after registration is pending, then simply drop the request
			if($data['purpose'] == 'Official' && $data['hod_status'] == 'Pending')
			{
				$this->sah_booking_model->cancel_request($app_num); //function arguments: app_num, status column to be set to cancelled
				$this->notification->notify($data['user_id'], $user_auth, 'SAH Booking Cancellation', 'Request for SAH Room Booking Cancellation (Application No. : '.$app_num.') has been Approved successfully.', 'sah_booking/booking_request/details/'.$app_num.'/'.$user_auth);
				$this->session->set_flashdata('flashSuccess', 'Booking Cancellation has been approved successfully.');
				redirect('sah_booking/booking/history');
			}
			else if($data['purpose'] == 'Personal' && $data['ctk_allotment_status'] == 'Pending')
			{
				$this->sah_booking_model->cancel_request($app_num);
				$this->session->set_flashdata('flashSuccess', 'Booking Cancellation has been approved successfully.');
				redirect('sah_booking/booking/history');
			}
			//if the first recepient of notification is HOD, ask him to approve cancellation
			else if($data['purpose'] == 'Official' && $data['hod_status'] == 'Approved')
			{
				$this->sah_booking_model->cancel($app_num, 'hod_status');
				$this->notification->notify($to_id, $to_auth, 'SAH Booking Cancellation', 'Request for SAH Room Booking Cancellation for Application No. : '.$app_num, 'sah_booking/booking_request/cancel/'.$app_num.'/'.$to_auth);
			}
			//if the first recepient of notification is CTK, but est_ar has not yet approved, then drop it and send notification to caretaker
			else if($data['ctk_allotment_status'] == 'Approved' && $data['est_ar_status'] == 'Pending')
			{
				$this->sah_booking_model->cancel_request($app_num);
				$this->notification->notify($est_da4, 'est_da4', 'SAH Booking Cancellation', 'Request for SAH Room Booking (Application No. : '.$app_num.') has been Cancelled by Applicant', 'sah_booking/booking_request/details/'.$app_num.'/est_da4');
				$this->notification->notify($data['user_id'], $user_auth, 'SAH Booking Cancellation', 'Request for SAH Room Booking Cancellation (Application No. : '.$app_num.') has been Approved successfully.', 'sah_booking/booking_request/details/'.$app_num.'/'.$user_auth);
				$this->session->set_flashdata('flashSuccess', 'Booking Cancellation has been approved successfully.');
				redirect('sah_booking/booking/history');
			}
			else if($data['est_ar_status'] == 'Approved')
			{
				$this->sah_booking_model->cancel($app_num, 'est_ar_status');
				$this->notification->notify($est_ar, 'est_ar', 'SAH Booking Cancellation', 'Request for SAH Room Booking Cancellation for Application No. : '.$app_num, 'sah_booking/booking_request/cancel/'.$app_num.'/est_ar');
			}

			$this->session->set_flashdata('flashSuccess', 'Booking Cancellation Request has been successfully sent.');
			redirect('sah_booking/booking/track_status');
		}
		else if($auth == 'stu')
		{
			if($data['dsw_status'] == 'Pending'){
				$this->sah_booking_model->cancel_request($app_num);
				$this->notification->notify($data['user_id'], $user_auth, 'SAH Booking Cancellation', 'Request for SAH Room Booking Cancellation (Application No. : '.$app_num.') has been Approved successfully.', 'sah_booking/booking_request/details/'.$app_num.'/'.$user_auth);
				$this->session->set_flashdata('flashSuccess', 'Booking Cancellation has been approved successfully.');
				//redirect('sah_booking/booking/history');
			}
			else if($data['dsw_status'] == 'Approved'){
				$this->sah_booking_model->cancel($app_num, 'dsw_status');
				$this->notification->notify($to_id, 'dsw', 'SAH Booking Cancellation', 'Request for SAH Room Booking Cancellation for Application No. : '.$app_num, 'sah_booking/booking_request/cancel/'.$app_num.'/dsw');
			}

			$this->session->set_flashdata('flashSuccess', 'Booking Cancellation Request has been successfully sent.');
			redirect('sah_booking/booking/track_status');
		}
		else if($auth == 'hod' || $auth == 'hos' || $auth == 'dsw')
		{
			//if after approval of cancellation by hod/hos or dsw, ctk is yet to allot rooms, then drop request
			if($data['ctk_allotment_status'] == 'Pending'){
				$this->sah_booking_model->cancel_request($app_num);
				$this->notification->notify($data['user_id'], $user_auth, 'SAH Booking Cancellation', 'Request for SAH Room Booking Cancellation (Application No. : '.$app_num.') has been Approved successfully.', 'sah_booking/booking_request/details/'.$app_num.'/'.$user_auth);
			}
			//if after approval of cancellation by hod/hos or dsw, est_ar is pending then drop request and send notification to ctk
			else if($data['est_ar_status'] == 'Pending')
			{
				$this->sah_booking_model->cancel_request($app_num);
				$this->notification->notify($est_da4_id, 'est_da4', 'SAH Booking Cancellation', 'Request for SAH Room Booking (Application No. : '.$app_num.') has been Cancelled by Applicant', 'sah_booking/booking_request/details/'.$app_num.'/est_da4');
				$this->notification->notify($data['user_id'], $user_auth, 'SAH Booking Cancellation', 'Request for SAH Room Booking Cancellation (Application No. : '.$app_num.') has been Approved successfully.', 'sah_booking/booking_request/details/'.$app_num.'/'.$user_auth);
			}
			//if after approval of cancellation by hod/hos or dsw, est_ar is approved then send cancellation request to est_ar
			else if($data['est_ar_status'] == 'Approved')
			{
				$res = $this->user_model->getUsersByDeptAuth('all', 'est_ar'); //get the users to whom approval/rejection requests are to be sent
				foreach($res as $row)
					$to_id = $row->id;
				$this->sah_booking_model->cancel($app_num, 'est_ar_status');
				$this->notification->notify($to_id, 'est_ar', 'SAH Booking Cancellation', 'Request for SAH Room Booking Cancellation for Application No. : '.$app_num, 'sah_booking/booking_request/cancel/'.$app_num.'/est_ar');
			}

			$this->session->set_flashdata('flashSuccess', 'Booking Cancellation Request has been Approved.');
			redirect('sah_booking/booking_request/app_list/'.$auth);
		}
		else if($auth == 'est_ar')
		{
			$this->sah_booking_model->cancel_request($app_num);
			//to hod/hos/dsw
			if($data['purpose'] == 'Official')
				$this->notification->notify($to_id, $to_auth, 'SAH Booking Cancellation', 'SAH Room Booking (Application No. : '.$app_num.') has been Cancelled by EST Assist. Registrar.', 'sah_booking/booking_request/cancel/'.$app_num.'/'.$to_auth);
			//to est_da4
			$this->notification->notify($est_da4_id, 'est_da4', 'SAH Booking Cancellation', 'SAH Room Booking (Application No. : '.$app_num.') has been Cancelled by EST Assist. Registrar.', 'sah_booking/booking_request/details/'.$app_num.'/est_da4');
			$this->notification->notify($est_da5_id, 'est_da5', 'SAH Booking Cancellation', 'SAH Room Booking (Application No. : '.$app_num.') has been Cancelled by EST Assist. Registrar.', 'sah_booking/booking_request/details/'.$app_num.'/est_da5');
			//to user
			$this->notification->notify($data['user_id'], $user_auth, 'SAH Booking Cancellation', 'SAH Room Booking (Application No. : '.$app_num.') has been Cancelled by EST Assist. Registrar.', 'sah_booking/booking_request/details/'.$app_num.'/'.$user_auth);

			$this->session->set_flashdata('flashSuccess', 'Booking Cancellation Request has been Approved.');
			redirect('sah_booking/booking_request/app_list/'.$auth);
		}
	}
}
