<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Guest_details extends MY_Controller {
	function __construct() {
		parent::__construct(array('est_da5', 'est_da4', 'est_ar'));

		$this->load->model ('sah_booking/sah_booking_model', '', TRUE);
		$this->load->model('user_model');
		$this->load->model('sah_booking/sah_allotment_model');

		date_default_timezone_set('Asia/Kolkata');
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

	function index()
	{
		$this->auth_is('est_da5');
		$res = $this->sah_booking_model->get_allotted_applications();

		$total_rows_approved = count($res);
		$data_array_approved = array();

		$sno = 0;
		foreach ($res as $row)
		{
			$data_array_approved[$sno]=array();
			$j=1;
			$data_array_approved[$sno][$j++] = $row['app_num'];
			$data_array_approved[$sno][$j++] = $this->user_model->getNameById($row['user_id']);
			$data_array_approved[$sno][$j++] = date('j M Y g:i A', strtotime($row['check_in']));
			$data_array_approved[$sno][$j++] = date('j M Y g:i A', strtotime($row['check_out']));
			$data_array_approved[$sno][$j++] = $row['no_of_guests'];
			$sno++;
		}
		$data['data_array_approved'] = $data_array_approved;
		$data['total_rows_approved'] = $total_rows_approved;

		$this->drawHeader('Senior Academic Hostel');
		$this->load->view('sah_booking/show_alloted_applications', $data);
		$this->drawFooter();
	}

	function edit($app_num='')
	{
		$this->auth_is('est_da5');
		$data = array();
		$data['app_details'] = $this->sah_booking_model->get_booking_details($app_num);
		$data['room_booking_details'] =  $this->sah_booking_model->get_rooms_for_application($app_num);

		//get user_id against the app_num
		$data['user_id'] = $this->sah_booking_model->get_request_user_id($app_num);

//-->	//the code below finds out the groups and rooms associated to each group
		$res = $this->sah_booking_model->get_guest_groups($app_num);
		$data['guest_details'] = array();
		$sno = 0;
		foreach($res as $row)
		{
			$data['guest_details'][$sno] = $row;
			$room_res = $this->sah_booking_model->get_guest_rooms($row['app_num'], $row['name'], $row['check_in']); //got the room id
			$i = 0;
			//now get the room details
			foreach($room_res as $room_row)
			{
				$room_info = $this->sah_allotment_model->get_room_details($room_row['room_alloted']);
				if($room_info)
					$data['guest_details'][$sno]['rooms'][$i++] = ucfirst($room_info['building']).' - '.ucfirst($room_info['floor']).' - '.$room_info['room_no'].' - '.ucfirst($room_info['room_type']);
				else $data['guest_details'][$sno]['rooms'][$i++] = 'Room Info Unavailable!';
			}
			$sno++;
		}
//-->
		//to show guest number in drop down menu for number of guests, need to find out how many guests are entered for the app_num
		//depending on how many guests are left to checkin, it'll show the number of guests for group tab
		$data['count_guest']=count($this->sah_booking_model->get_guest_details($app_num));

		//to show only available rooms from list of allotted rooms,
		// -> get list of allotted rooms
		// -> get rooms that are not full
		$allotted_rooms = $this->sah_allotment_model->get_allocated_room_details($app_num);
		$data['no_of_rooms'] = count($allotted_rooms);
		$sno = 0;
		$data['rooms_left'] = 0;
		foreach($allotted_rooms as $allotted_room)
		{
			$type = $this->sah_allotment_model->get_room_details($allotted_room['room_id'])['room_type'];
			$entries = $this->sah_booking_model->get_guest_entries($app_num, $allotted_room['room_id']);
			//echo $type.' - '.$allotted_room['room_id'].' - '.$entries.'<br/>';
			if(($type == 'AC Suite' && $entries == 1) || ($type == 'Double Bedded AC' && $entries == 2)) //if room is full then skip this room
				continue;
			else
			{	//add current room to list of available rooms
				//echo 'adding -> '.$allotted_room['room_id'].'<br/>';
				$data['rooms'][$sno++] = $this->sah_allotment_model->get_room_details($allotted_room['room_id']);
				$data['rooms_left']++;
			}
		}

		$this->drawHeader('Add Guest Details');
		$this->load->view('sah_booking/add_checkin_checkout',$data);
		$this->drawFooter();

	}

	function insert_guest()
	{
		$this->auth_is('est_da5');

		$data = array('app_num'=>$this->input->post('app_num'),
					  'name'=>filter_var($this->input->post('name'), FILTER_SANITIZE_STRING),
					  'designation'=>filter_var($this->input->post('designation'), FILTER_SANITIZE_STRING),
					  'address'=>trim($this->input->post('address')),
					  'gender'=>$this->input->post('gender'),
					  'contact'=>filter_var($this->input->post('contact'), FILTER_SANITIZE_NUMBER_INT),
					  'email'=>filter_var($this->input->post('email'), FILTER_SANITIZE_EMAIL));

		$b_detail = $this->sah_booking_model->get_booking_details($data['app_num'])[0];
		//checking in before check in date is error
		//this restriction is put because if any applicant checks in before his appointed time
		//then there might be clashes with other bookings
		if(time() < strtotime($b_detail['check_in'])) {
			$this->session->set_flashdata('flashError', 'Checking In before Check In DateTime not allowed');
			redirect('sah_booking/guest_details');
		}
		//get identity card
		$upload = $this->upload_file('identity_card' , $data['app_num']);
		if ($upload)
			$data['identity_card'] = $upload['file_name'];
		//if group type, then add same entry to the rooms allocated
		$type_of_booking = $this->input->post('type_of_booking');
		if($type_of_booking == 'group')
		{
			$rooms_chosen = $this->input->post('ckbox_rooms'); //getting only room id
			$group_guests = $this->input->post('group_guests');

			//insert suite ac
			$count = 0;
			foreach($rooms_chosen as $room)
			{
				if($this->sah_allotment_model->get_room_details($room)['room_type'] == "AC Suite")
				{
					echo 'SAC Room '.$room.', inserting data.<br/>';
					$data['room_alloted'] = $room;
					if($count < $group_guests)
					{
						$this->sah_booking_model->insert_guest_details($data);
						$this->sah_booking_model->set_check_in($data['app_num']);
						$count++;
					}
				}
			}

			//insert double ac
			foreach($rooms_chosen as $room)
			{
				if($this->sah_allotment_model->get_room_details($room)['room_type'] == "Double Bedded AC")
				{
					$data['room_alloted'] = $room;
					$entries = $this->sah_booking_model->get_guest_entries($data['app_num'], $room);
					if($entries == 0)
					{	//insert two entries
						if($count < $group_guests)
						{
							$this->sah_booking_model->insert_guest_details($data);
							$count++;
						}
						if($count < $group_guests)
						{
							$this->sah_booking_model->insert_guest_details($data);
							$count++;
						}
					}
					else if($entries == 1)
					{	//insert one entry
						if($count < $group_guests)
						{
							$this->sah_booking_model->insert_guest_details($data);
							$count++;
						}
					}
				}
			}
		}
		else if($type_of_booking == 'individual'){
			$data['room_alloted'] = $this->input->post('room_alloted');
			$this->sah_booking_model->insert_guest_details($data);
		}

		$this->session->set_flashdata('flashSuccess','Check In Successful.');
		redirect('sah_booking/guest_details/edit/'.$this->input->post('app_num'));
	}

	function bill_total_sum($app_num, $name, $check_in) {
		$rooms = $this->sah_booking_model->get_guest_rooms($app_num, $name, $check_in);
		$room_details = array();
		//get whether double ac room is shared, or single
		$sno = 0;
		foreach($rooms as $room)
		{
			$room_details[$sno] =  $this->sah_allotment_model->get_room_details($room['room_alloted']);
			if($room_details[$sno]['room_type'] == 'Double Bedded AC')
				$room_details[$sno]['single'] = $this->sah_booking_model->check_single_room($app_num, $name, $check_in, $room['room_alloted']);	//0 -> single in total (400/600), 1 -> single in group(200/300), 2 -> double in group (400/600)
			$sno++;
		}

		$booking_details = $this->sah_booking_model->get_booking_details($app_num)[0];
		$purpose = $booking_details['purpose'];
		$school_guest = $booking_details['school_guest'];
		$check_out = $booking_details['check_out'];

		$day_time = 86400;
		$sno = 1;
		$total_sum = 0;
		foreach($room_details as $room)
		{
			if($purpose == 'Official')
			{
				if($room['room_type'] == 'Double Bedded AC')
				{
					if($room['single'] == 0 || $room['single'] == 2)
						$tariff = 400;
					else $tariff = 200;
				}
				else $tariff = 800;
			}
			else
			{
				if($room['room_type'] == 'Double Bedded AC')
				{
					if($room['single'] == 0 || $room['single'] == 2)
						$tariff = 800;
					else $tariff = 400;
				}
				else $tariff = 1200;
			}
			if($school_guest == '0')
				$subtotal = ceil((strtotime($check_out) - strtotime($check_in)) / $day_time) * $tariff;
			else $subtotal = 0;
			$total_sum += $subtotal;
		}
		return $total_sum;
	}

	function generate_bill($app_num, $name, $check_in) {
			$this->auth_is('est_da5');

			$booking_details = $this->sah_booking_model->get_booking_details($app_num)[0];
			$tariff = $this->sah_booking_model->get_tariff($app_num);
			$total_sum = $this->bill_total_sum($app_num, $name, $check_in);
			//get basic details to show in receipt
			$data = $this->sah_booking_model->get_group_details($app_num, $name, $check_in); //returns app_num, name, designation, gender, address, email, contact, room_alloted, check_in and check_out, id_path
			//get rooms for that group
			$rooms = $this->sah_booking_model->get_guest_rooms($app_num, $name, $check_in);
			$room_details = array();
			//get whether double ac room is shared, or single
			$sno = 0;
			foreach($rooms as $room) {
				$room_details[$sno] =  $this->sah_allotment_model->get_room_details($room['room_alloted']);
				if($room_details[$sno]['room_type'] == 'Double Bedded AC') {
					$room_details[$sno]['tariff'] = $tariff['double_'.strtolower($booking_details['purpose'])];
					$room_details[$sno]['single'] = $this->sah_booking_model->check_single_room($app_num, $name, $check_in, $room['room_alloted']);	//0 -> single in total (400/600), 1 -> single in group(200/300), 2 -> double in group (400/600)
				}
				else $room_details[$sno]['tariff'] = $tariff['suite_'.strtolower($booking_details['purpose'])];
				$sno++;
			}
			$data['paid'] = $total_sum;
			$data['rooms'] = $room_details;
			$data['purpose'] = $booking_details['purpose'];
			$data['school_guest'] = $booking_details['school_guest'];
			$this->load->view('sah_booking/bill', $data);
	}

	function generate_receipt($app_num, $name, $check_in) {
		$this->auth_is('est_da5');
		//set guest payment data
		$name = urldecode($name);
		$check_in = urldecode($check_in);
		$booking_details = $this->sah_booking_model->get_booking_details($app_num)[0];
		$tariff = $this->sah_booking_model->get_tariff($app_num);

		$total_sum = $this->bill_total_sum($app_num, $name, $check_in);
		$this->sah_booking_model->set_paid_data($app_num, $name, $check_in, $total_sum);
		//get basic details to show in receipt
		$data = $this->sah_booking_model->get_group_details($app_num, $name, $check_in); //returns app_num, name, designation, gender, address, email, contact, room_alloted, check_in and check_out, id_path
		//get rooms for that group
		$rooms = $this->sah_booking_model->get_guest_rooms($app_num, $name, $check_in);
		$room_details = array();
		//get whether double ac room is shared, or single
		$sno = 0;
		foreach($rooms as $room) {
			$room_details[$sno] =  $this->sah_allotment_model->get_room_details($room['room_alloted']);
			if($room_details[$sno]['room_type'] == 'Double Bedded AC') {
				$room_details[$sno]['tariff'] = $tariff['double_'.strtolower($booking_details['purpose'])];
				$room_details[$sno]['single'] = $this->sah_booking_model->check_single_room($app_num, $name, $check_in, $room['room_alloted']);	//0 -> single in total (400/600), 1 -> single in group(200/300), 2 -> double in group (400/600)
			}
			else $room_details[$sno]['tariff'] = $tariff['suite_'.strtolower($booking_details['purpose'])];
			$sno++;
		}
		$data['rooms'] = $room_details;
		$data['purpose'] = $booking_details['purpose'];
		$data['school_guest'] = $booking_details['school_guest'];
		$this->load->view('sah_booking/bill_receipt', $data);
	}

	function add_checkout($app_num,$room_alloted,$guest_name) {
		$this->auth_is('est_da5');
		$this->sah_booking_model->checkout($app_num,$room_alloted,$guest_name);
		$this->sah_booking_model->set_check_out($app_num);
		$this->session->set_flashdata('flashSuccess','Check Out Successfull.');
		redirect('sah_booking/guest_details/edit/'.$app_num);
	}

 	function upload_file($name ='', $app_num='')
	{
		$this->auth_is('est_da5');
		$config['upload_path'] = 'assets/files/sah_booking/'.$this->sah_booking_model->get_request_user_id($app_num).'/';
		$config['allowed_types'] = 'jpg|jpeg|png';
		$config['max_size']  = '1024';

		if(isset($_FILES[$name]['name']))
    	{
            if($_FILES[$name]['name'] == "")
        		$filename = "";
            else
			{
                $filename=$this->security->sanitize_filename(strtolower($_FILES[$name]['name']));
                $ext =  strrchr( $filename, '.' ); // Get the extension from the filename.
                $filename='IDFILE_'.$app_num.$ext;
            }
        }
        else
        {
        	$this->session->set_flashdata('flashError','ERROR: File Name not set.');
        	redirect('sah_booking/guest_details/');
			return FALSE;
        }

		$config['file_name'] = $filename;

		if(!is_dir($config['upload_path']))	//create the folder if it's not already exists
		{
			mkdir($config['upload_path'],0777,TRUE);
		}

		$this->load->library('upload', $config);

		if ( ! $this->upload->do_multi_upload($name))		//do_multi_upload is back compatible with do_upload
		{
			$this->session->set_flashdata('flashError',$this->upload->display_errors('',''));
			redirect('sah_booking/guest_details/');
			return FALSE;
		}
		else
		{
			$upload_data = $this->upload->data();
			return $upload_data;
		}
	}

	function search($auth)
	{
		$this->drawHeader('SAH Guest History Search');
		$this->load->view('sah_booking/room_booking_history', array('auth' => $auth));
		$this->drawFooter();
	}

	function display_booking_history(){
		$data = array('name'=>$this->input->post('name'),
					  'rooms'=> $this->input->post('checkbox_rooms'));

		$check_in = date("Ymd",strtotime($this->input->post('check_in')));
		$check_out = date("Ymd",strtotime($this->input->post('check_out')));
		$date = date("Ymd",strtotime($this->input->post('date')));

		$data['check_in'] = $check_in;
		$data['check_out'] = $check_out;
		$data['date'] = $date;

		$occupance_history['auth'] = $this->input->post('auth');
		$occupance_history['booking_history'] = $this->sah_booking_model->get_room_occupance_history($data);

		foreach($occupance_history['booking_history'] as $key => $data)
		{
			$occupance_history['booking_history'][$key]['room_details'] = $this->sah_allotment_model->get_room_details($data['room_alloted']);
			//for each guest under same app num, list all rooms it has been allotted
			foreach($this->sah_booking_model->get_booking_details($data['app_num']) as $res)
				$occupance_history['booking_history'][$key]['purpose'] = $res['purpose'];
				//funds start
				//for displaying funds
				$data = $this->sah_booking_model->get_group_details($data['app_num'], $data['name'], $data['check_in']); //returns app_num, name, designation, gender, address, email, contact, room_alloted, check_in and check_out, id_path
				//get rooms for that group
				$rooms = $this->sah_booking_model->get_guest_rooms($data['app_num'], $data['name'], $data['check_in']);
				$room_details = array();
				//get whether double ac room is shared, or single
				$sno = 0;
				foreach($rooms as $room)
				{
					$room_details[$sno] =  $this->sah_allotment_model->get_room_details($room['room_alloted']);
					if($room_details[$sno]['room_type'] == 'Double Bedded AC')
						$room_details[$sno]['single'] = $this->sah_booking_model->check_single_room($data['app_num'], $data['name'], $data['check_in'], $room['room_alloted']);	//0 -> single in total (400/600), 1 -> single in group(200/300), 2 -> double in group (400/600)
					$sno++;
				}
				$data['rooms'] = $room_details;
				foreach($this->sah_booking_model->get_booking_details($data['app_num']) as $row){
					$data['purpose'] = $row['purpose'];
					$data['school_guest'] = $row['school_guest'];
				}
				$occupance_history['booking_history'][$key] = $data;
				//funds end
			}

		$this->drawHeader('SAH Guest History Details');
		$this->load->view('sah_booking/room_booking_history_view',$occupance_history);
		$this->drawFooter();
	}

	function room_planning($building)
	{
		if($this->sah_allotment_model->no_of_rooms($building) === 1) {
			$this->load->view('sah_booking/no_room_data.php');
		}
		else {
			$result_uavail_rooms = $this->sah_allotment_model->check_unavail(date('Y-m-d H:i:s'),date('Y-m-d H:i:s'));
			$floor_array = $this->sah_allotment_model->get_floors($building);

			$flr = 1;
			foreach($floor_array as $floor)
			{
				$temp_query = $this->sah_allotment_model->get_rooms($building,$floor['floor']);
				$result_floor_wise[$flr][0] = $temp_query;
				$result_floor_wise[$flr++][1] = $floor['floor'];
			}

			$data_array = array();
			$i = 0;
			foreach($result_floor_wise as $floor)
			{
				$sno=1;
				$data_array[$i][0] = $floor[1];
				foreach($floor[0] as $row)
				{
					$flag=0;
					foreach($result_uavail_rooms as $room_unavailable)
					{
						if($row['id']==$room_unavailable['room_id'])
							$flag = 1;
					}
					$data_array[$i][$sno][0] = $row['id'];
					$data_array[$i][$sno][1] = $row['room_no'];
					$data_array[$i][$sno][2] = $row['room_type'];
					if($flag==0)
						$data_array[$i][$sno][3] = 1;
					else
						$data_array[$i][$sno][3] = 0;
					$data_array[$i][$sno][4] = $row['blocked'];
					$data_array[$i][$sno++][5] = $row['remark'];
				}
				$i++;
			}

			$data['building'] = $building;
			$data['floor_room_array'] = $data_array;
			$data['room_array'] = $this->sah_allotment_model->get_room_types();
			$this->load->view('sah_booking/room_checkbox',$data);
		}
	}

	function show_guest_details($user_id, $app_num, $name, $check_in)
	{
		$this->auth_is('est_da5');
		//get the guest details
		$res = $this->sah_booking_model->get_guest_info($app_num, $name, $check_in);
		$res->user_id = $user_id;
		$this->drawHeader("Senior Academic Hostel");
		$this->load->view('sah_booking/guest_info', $res);
		$this->drawFooter();
	}
}
