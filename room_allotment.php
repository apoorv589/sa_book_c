<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
est_da4_action ($app_num)
get_room_plans ($building, $check_in, $check_out)
insert_sah_allotment ($app_num)
*/

class Room_allotment extends MY_Controller
{
	function __construct()
	{
		parent::__construct(array('est_da4', 'est_ar'));
		$this->addJS("sah_booking/booking.js");

		$this->load->model ('sah_booking/sah_allotment_model');
		$this->load->model ('sah_booking/sah_booking_model');
		$this->initialize_buildings();
	}

	function initialize_buildings() {
		$initializer_id = array('old' => 0,
								'extension' => 1
		);
		$this->initialize_building('old', $initializer_id);
		$this->initialize_building('extension', $initializer_id);
	}

	function initialize_building($building = '', $initializer_id) {
		if($this->sah_allotment_model->no_of_rooms($building) === 0) {
			$this->sah_allotment_model->initialize_building($building, $initializer_id[$building]);
		}
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

	function est_da4_action($app_num)
	{
		$this->auth_is('est_da4');

		$res = $this->sah_booking_model->get_booking_details($app_num);
		if($res[0]['hod_status'] === 'Cancel' ||
			$res[0]['hod_status'] === 'Cancelled' ||
			$res[0]['dsw_status'] === 'Cancel' ||
			$res[0]['dsw_status'] === 'Cancelled' ||
			$res[0]['est_ar_status'] === 'Cancelled') {
				$this->session->set_flashdata('flashError','Cannot allot room! Applicant has cancelled booking request.');
				redirect('sah_booking/booking_request/est_da4_app_list');
		}
		$data = array();

		foreach($res as $row)
		{
			$data = array(
				'check_in' => $row['check_in'],
				'check_out' => $row['check_out'],
				'double_AC' => $row['double_AC'],
				'suite_AC' => $row['suite_AC'],
				'est_ar_status' => $row['est_ar_status']
			);
		}
		$data['app_num'] = $app_num;

		$this->drawHeader ("Room Allotment");
		$this->load->view('sah_booking/sah_allotment_view',$data);
		$this->drawFooter();
	}

	function get_room_plans($building, $check_in = '', $check_out = '')
	{
		if($this->sah_allotment_model->no_of_rooms($building) === 1) {
			$this->load->view('sah_booking/no_room_data.php');
		}
		else {
			$result_uavail_rooms = $this->sah_allotment_model->check_unavail($check_in,$check_out);
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
					{
						$data_array[$i][$sno][3] = 1;
					}
					else
					{
						$data_array[$i][$sno][3] = 0;
					}
					$data_array[$i][$sno][4] = $row['blocked'];
					$data_array[$i][$sno++][5] = $row['remark'];
				}
				$i++;
			}
			$data['floor_room_array'] = $data_array;
			$data['room_array'] = $this->sah_allotment_model->get_room_types();
			$this->load->view('sah_booking/sah_rooms',$data);
		}
	}

	function insert_sah_allotment($app_num)
	{
		$this->auth_is('est_da4');

		$booking_details = $this->sah_booking_model->get_booking_details($app_num);
		foreach($booking_details as $b_detail) {
			if($b_detail['ctk_allotment_status'] === 'Approved') {
				$this->session->set_flashdata('flashError','Invalid attempt to allot room. Room Allotment has already been done.');
				redirect('sah_booking/booking_request/est_da4_app_list');
			}
			else if($b_detail['hod_status'] === 'Cancel' ||
				$b_detail['hod_status'] === 'Cancelled' ||
				$b_detail['dsw_status'] === 'Cancel' ||
				$b_detail['dsw_status'] === 'Cancelled' ||
				$b_detail['est_ar_status'] === 'Cancelled') {
					$this->session->set_flashdata('flashError','Cannot allot room! Applicant has cancelled booking request.');
					redirect('sah_booking/booking_request/est_da4_app_list');
			}
		}

		$double_bedded_ac = $this->input->post('checkbox_double_bedded_ac');
		$ac_suite = $this->input->post('checkbox_ac_suite');

		if(gettype($double_bedded_ac) == 'array' && gettype($ac_suite) == 'array')
			$room_list = array_merge($double_bedded_ac, $ac_suite);
		else if(gettype($double_bedded_ac) == 'array')
			$room_list = $double_bedded_ac;
		else $room_list = $ac_suite;

		$this->sah_allotment_model->set_ctk_status("Approved", $app_num);

		foreach($room_list as $room)
		{
			$input_data = array(
				'app_num' => $app_num,
				'room_id'	=> $room,
			);
			$this->sah_allotment_model->insert_booking_details ($input_data);
		}
		$this->load->model ('user_model');
		$res = $this->user_model->getUsersByDeptAuth('all', 'est_ar');
		$est_ar = '';
		foreach ($res as $row)
		{
			$est_ar = $row->id;
			$this->notification->notify ($est_ar, "est_ar", "Approve/Reject Pending Request", "SAH Room Booking Request (Application No. : ".$app_num." ) is Pending for your approval.", "sah_booking/booking_request/details/".$app_num."/est_ar", "");
		}
		$this->session->set_flashdata('flashSuccess','Room Allotment has been done successfully.');
		redirect('sah_booking/booking_request/est_da4_app_list');
	}
}
