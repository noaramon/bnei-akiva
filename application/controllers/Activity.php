<?php


class Activity extends CI_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->load->model('Activity_model');
		$this->load->model('Member_model');
		$this->load->model('Message_model');
		$this->load->model('Guide_model');
		$this->load->library('session');
	}

	//display_next_activities_and_its_rate
	public function activities()
	{
		$guide_email=$this->session->user->email;
		//echo "console.log(".print_r($guide_email).")";
		$data=$this->input->post();
		if($data['all']=='true'){
			$out=$this->Activity_model->get_all_activities_with_rate($guide_email);
			
		}
		else{
			$out=$this->Activity_model->get_top_3_activities_by_desc_order($guide_email);
		}
		
		echo json_encode($out);
	}

	///////////////////////////New/////////////////////////////////////////////////
	public function activitiesForParent()
	{
		$parent_email=$this->session->user->email;
		$data=$this->input->post();
		if($data['all']=='true'){
			$out=$this->Activity_model->get_activity_for_kids_by_parent_mail_top_3($parent_email);
			
		}
		else{
			$out=$this->Activity_model->get_activity_for_kids_by_parent_mail_top_all($parent_email);
		}
		
		echo json_encode($out);
	}
	///////////////////////////New/////////////////////////////////////////////////


	public function add_activity()
	{
		$type = $this->session->type;
		$users_email = $this->session->user->email;
		$time = $this->input->post('date').' '.$this->input->post('time').':00';
		$agegrade_id=$this->Guide_model->get_agegrade_by_email($users_email);
		// $time = date_create_from_format("Y-M-j H:i", $this->input->post('date').' '.$this->input->post('time') );
		$data = array(
			'name' => $this->input->post('name'),
			'description' => $this->input->post('description'),
			'time' => $time ,
			'guide_email' => $this->session->user->email,
			'agegrade_id' => $agegrade_id
		);

		$error = $this->Activity_model->save_activity($data);
		if ($error) {
			echo json_encode(array('error' => true,'db_error' => $error['message']));
			return;
		}
		echo json_encode(array('success' => true));
	}

	//add_after_summary_and_send_notifications
	public function add_summary()
	{
		$counter = 0;
		$members_arr=$this->input->post("members");
		
		foreach ($members_arr as $member) {
			if ($member['attendant'] == 1) {
				$counter++;
				$subject='החניך הגיע';
			}
			else{
				$subject='החניך לא הגיע';
			}
			$email=$member['email'];
			$get_parent= $this->Member_model->get_member($email);
			$parent=$get_parent[0]->parent_email;
			$message= array(
				'sent_from' => $this->session->user->fname.' '.$this->session->user->lname,
				'subject' => $subject,
				'recipient_email'=> $parent
			);
			$this->Message_model->send($message);
		}
		$id = $this->input->post('id');
		$newdata=array(
			
			'after_summary' => $this->input->post('after_summary'),
			'num_participants'=>$counter
		);
		$error = $this->Activity_model->update($id,$newdata);
		if ($error) {
			$errors = array('error' => true,'db_error' => $error);
			echo json_encode($errors);
			return;
		}

		echo json_encode(array('success' => true));
	}

	public function substitutes()
	{
		$guide_email=$this->session->user->email;
		
		$agegrade_id=$this->Guide_model->get_agegrade_by_email($guide_email);

		$data=$this->input->post();
		$out=$this->Activity_model->get_substitute_by_agegrade_order_by_activity_time_DESC($agegrade_id);
		echo json_encode($out);
	}

	public function substitute_request()
	{
		$activity_id=$this->input->post('activity_id');
		$guide_email=$this->session->user->email;
		$data = array(
			'activity_id' => $activity_id,
			'guide_email' => $guide_email,
			'agegrade_id' => $this->Guide_model->get_agegrade_by_email($guide_email)
		);

		$this->Activity_model->update($activity_id,array('sub_req'=>1));

		$error = $this->Activity_model->add_substitute($data);
		if ($error) {
			echo json_encode(array('error' => true,'db_error' => $error['message']));
			return;
		}

		echo json_encode(array('success' => true));

	}

	public function change_guide()
	{
		$id=$this->input->post('id');
		$data=array('guide_email'=>$this->session->user->email, 'sub_req'=>0);
		$activity_id=$this->input->post('activity_id');
		$this->Activity_model->update($activity_id,$data);

		
		$this->Activity_model->delete_substitute_by_id($id);
		echo json_encode(array('success' => true));
	}

	public function add_rate()
	{
		$data=array(
			'name'=>$this->input->post('name'), //??
			'after_summary'=>$this->input->post('after_summary'),
			'num_participates'=>$this->input->post('num_participates')
		);

		$error = $this->Activity_model->add_rate($data);
		if ($error) {
			$errors = array('error' => true,'db_error' => $error);
			echo json_encode($errors);
			return;
		}

		echo json_encode(array('success' => true));

	}

	public function get_activity_details(){
		$activity_id=$this->input->post('activity_id');
		$agegrade = $this->Activity_model->get_agegrade_by_activity_id($activity_id);
		$agegrade_id=$agegrade[0]->agegrade_id;
		$members=$this->Member_model->get_members_by_agegrade($agegrade_id);
		$members_declare= $this->Activity_model->get_health_declare_by_activity($activity_id);

		$data=$this->Activity_model->get_activity_with_rate_by_id($activity_id);

		$members_to_send=array();
		foreach($members as $row1)
		{
			$member = array();
			$member['email']=$row1->users_email;
			$member['attendant']=0;
			$member['health_declare']=0;
			$member['member_name']=$row1->fname.' '.$row1->lname;

			if (!empty($members_declare) )
			{
				foreach($members_declare as $row2)
				{
					if ($row1->users_email==$row2->member_email)
					{
						$member['health_declare']=1;
						break;
					}
				}
				
			}
			array_push($members_to_send, $member);
		}
		$data['members']=$members_to_send;
		echo json_encode($data);
	}

	public function update_activity() {

		$time = $this->input->post('date').' '.$this->input->post('time').':00';
		

		$activity_id = $this->input->post("id");
		$data=$this->input->post();

		$data["time"]=$time;

		unset($data["id"]);
		unset($data["date"]);
		$this->Activity_model->update($activity_id,$data);

		echo json_encode(array('success' => true));
	}

}