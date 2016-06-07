<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Management extends MY_Controller
{
	function __construct()
	{
		parent::__construct(array('est_da4', 'est_ar'));

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
		if($this->sah_allotment_model->no_of_rooms($building) === 0)
			$this->sah_allotment_model->initialize_building($building, $initializer_id[$building]);
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

	function room_management()
	{
		$this->auth_is('est_da4');
		$this->drawHeader('Senior Academic Hostel');
		$this->load->view('sah_booking/sah_room_planning');
		$this->drawFooter();
	}

	function room_planning($building)
	{
		$this->auth_is('est_da4');

		$result_uavail_rooms = $this->sah_allotment_model->get_booked_rooms(date('Y-m-d H:i:s'));
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
		$this->load->view('sah_booking/room_plans',$data);
	}

	function room_status($room_id, $auth) {
		//CHECKED IN ROOM: get any application which has check in earlier than current date and has not checked out
		//get all bookings for the current room later in future
		$data['auth'] = $auth;
		$checked_app = $this->sah_allotment_model->get_checked_app($room_id);
		$room_bookings = $this->sah_allotment_model->get_room_bookings($room_id);
		if($checked_app)
			$data['checked_app'] = $this->sah_booking_model->get_booking_details($checked_app)[0];
		else $data['checked_app'] = '';

		$i = 0;
		foreach($room_bookings as $booking) {
			$data['room_bookings'][$i]['app_num'] = $booking['app_num'];
			$data['room_bookings'][$i++]['name'] = $this->sah_booking_model->get_booking_details($booking['app_num'])[0]['name'];
		}

		$this->load->view('sah_booking/room_status', $data);
	}

	function building_status($auth) {
		$this->addJS('sah_booking/room_availability.js');
		$data['auth'] = $auth;

		$this->drawHeader('Senior Academic Hostel');
		$this->load->view('sah_booking/building_status', $data);
		$this->drawFooter();
	}

	function load_building_status($building, $auth) {
		//fetch all room data with current room holders, booked rooms
		//make all rooms a link which will show the application to which room is allotted
		$data['auth'] = $auth;
		$result_uavail_rooms = $this->sah_allotment_model->get_booked_rooms(date('Y-m-d H:i:s'));
		$checked_in_rooms = $this->sah_allotment_model->get_checked_rooms();

		if($this->sah_allotment_model->no_of_rooms($building) <= 1)
			$this->load->view('sah_booking/no_room_data');
		else {
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
			foreach($result_floor_wise as $floor) {
				$sno=1;
				$data_array[$i][0] = $floor[1];
				foreach($floor[0] as $row)
				{
					$flag=0; //free
					foreach($result_uavail_rooms as $room_unavailable) //this can be optimized
					{
						if($row['id'] === $room_unavailable['room_id'])
							$flag = 1; //booked
					}
					foreach($checked_in_rooms as $c_room) {
						if($row['id'] === $c_room['room_id'])
							$flag = 2; //checked
					}
					$data_array[$i][$sno][0] = $row['id'];
					$data_array[$i][$sno][1] = $row['room_no'];
					$data_array[$i][$sno][2] = $row['room_type'];
					$data_array[$i][$sno][3] = $flag;
					$data_array[$i][$sno][4] = $row['blocked'];
					$data_array[$i][$sno++][5] = $row['remark'];
				}
				$i++;
			}

			$data['building'] = $building;
			$data['floor_room_array'] = $data_array;
			$data['room_array'] = $this->sah_allotment_model->get_room_types(); //creates two sections for room types
			$this->load->view('sah_booking/building_view', $data);
		}
	}

	function add_form($building, $floor, $type)
	{
		$this->auth_is('est_da4');
		$data = array(
			'building' => $building,
			'floor' => $floor,
			'type' => $type
		);

		$this->drawHeader('Senior Academic Hostel');
		$this->load->view('sah_booking/add_room', $data);
		$this->drawFooter();
	}

	function add_rooms()
	{
		$data = array(
			'room_no' => $this->input->post('room_no'),
			'building' => strtolower($this->input->post('building')),
			'floor' => strtolower(trim($this->input->post('floor'))),
			'room_type' => $this->input->post('type'),
			'remark' => $this->input->post('remark')
		);

		$this->sah_allotment_model->add_rooms($data);
		redirect('sah_booking/management/room_management');
	}

	function remove_rooms()
	{
		$rooms = $this->input->post('checkbox_rooms');
		$this->sah_allotment_model->remove_rooms($rooms);
		redirect('sah_booking/management/room_management');
	}

	function block_rooms()
	{
		$rooms = $this->input->post('checkbox_rooms');
		$remark = $this->input->post('remark');
		$this->sah_allotment_model->block_rooms($rooms, $remark);
		redirect('sah_booking/management/room_management');
	}

	function unblock_rooms()
	{
		$rooms = $this->input->post('checkbox_rooms');
		$this->sah_allotment_model->unblock_rooms($rooms);
		redirect('sah_booking/management/room_management');
	}
}
