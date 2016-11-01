<?php defined('BASEPATH') OR exit('No direct script access allowed');

class User extends Front_Controller
{
	const OAUTH_VER     = 1.0;
	const JIACCOUNT_URL = 'http://www.umji.sjtu.edu.cn/student';
	
	public function __construct()
	{
		parent::__construct();
		$this->Site_model->load_site_config('user');
	}
	
	protected function redirect()
	{
		$this->__redirect();
	}
	
	public function index()
	{
		
	}
	
	public function jiaccount()
	{
		$uri = $this->input->get('url');
		$logout = $this->input->get('logout');
		if (!filter_var($uri, FILTER_VALIDATE_URL))
		{
			echo 'url validation failed!';
			exit();
		}
		$query = array(
			'uri'       => $uri,
			'auth_type' => 'jiaccount'
		);
		if ($logout == '1')
		{
			redirect(base_url('user/logout?' . http_build_query($query)));
		}
		else
		{
			redirect(base_url('user/login?' . http_build_query($query)));
		}
	}
	
	public function jiaccount_logout()
	{
		$this->jiaccount_redirect($this->input->get('uri'), array('logout' => '1'));
	}
	
	protected function jiaccount_redirect($url, $query = array())
	{
		$parsed = parse_url($url);
		if (!isset($parsed['query']))
		{
			$url .= '?' . http_build_query($query);
		}
		else
		{
			$temp = array();
			parse_str($parsed['query'], $temp);
			$query = http_build_query($temp + $query);
			$url = preg_replace('/(?<=\?)(.*)/', $query, $url);
		}
		header('Location: ' . $url);
		exit();
	}
	
	public function login()
	{
		/** In the development mode, we will use ji-account api to login */
		if (ENVIRONMENT == 'development')
		{
			$result = $this->input->get('result');
			if ($result == 'success')
			{
				$_SESSION['user_id'] = $this->input->get('user_id');
				$_SESSION['username'] = $this->input->get('name');
				redirect(base_url($this->input->get('uri')));
			}
			header('Location: ' . $this::JIACCOUNT_URL . '/user/jiaccount?url='
			       . urlencode(base_url('user/login') . '?uri=' . $this->input->get('uri')));
			exit();
			
		}
		/** Here Jaccount have two versions: OAuth 1.0 & 2.0 */
		$redirect_query = array(
			'uri'       => $this->input->get('uri'),
			'auth_type' => $this->input->get('auth_type')
		);
		if ($this::OAUTH_VER >= 2.0)
		{
			$redirect_uri = base_url('user/auth2?' . http_build_query($redirect_query));
			$query = array(
				'response_type' => 'code',
				'client_id'     => 'jaji20150623',
				'redirect_uri'  => $redirect_uri
			);
			//echo http_build_query($query);
			header('Location: https://jaccount.sjtu.edu.cn/oauth2/authorize?' . http_build_query($query));
			exit();
		}
		else if ($this::OAUTH_VER >= 1.0)
		{
			redirect(base_url('user/auth1?' . http_build_query($redirect_query)));
		}
		
		
	}
	
	public function auth2()
	{
		$redirect_uri = base_url($this->input->get('uri'));
		$auth_code = $this->input->get('code');
		
		$url = 'https://jaccount.sjtu.edu.cn/oauth2/token';
		$post_data = array(
			'grant_type'    => 'authorization_code',
			'code'          => $auth_code,
			'redirect_uri'  => $redirect_uri,
			'client_id'     => $this->get_site_config('user_client_id'),
			'client_secret' => $this->get_site_config('user_client_secret')
		);
		
		$token_json = $this->Site_model->request_post($url, $post_data);
		$token_info = json_decode($token_json);
		
		if (isset($token_info->error))
		{
			echo $token_info->error;
			//$_SESSION["user_id"] = '515370910207';
			//$_SESSION["username"] = '刘逸灏';
			redirect($redirect_uri);
		}
		
		$url = "https://api.sjtu.edu.cn/v1/me/profile?access_token=" . $token_info->access_token;
		$usr_json = $this->Site_model->request_get($url);
		$usr_info = json_decode($usr_json);
		
		if ($usr_info->error != 0)
		{
			echo $usr_info->error;
			//$_SESSION["user_id"] = '515370910207';
			//$_SESSION["username"] = '刘逸灏';
			redirect($redirect_uri);
		}
		
		$_SESSION["user_id"] = $usr_info->entities[0]->code;
		$_SESSION["username"] = $usr_info->entities[0]->name;
		
		redirect($redirect_uri);
		
	}
	
	public function auth1()
	{
		$redirect_query = array(
			'uri'       => $this->input->get('uri'),
			'auth_type' => $this->input->get('auth_type')
		);
		$redirect_uri = ROOT_DIR . '/user/auth1?' . http_build_query($redirect_query);
		$this->load->library('JAccount');
		$jam = new JAccountManager('jaji20150623', 'jaccount');
		$ht = $jam->checkLogin($redirect_uri);
		/*print_r($ht);
		print_r($redirect_query);
		exit();*/
		if ($redirect_query['auth_type'] == 'jiaccount')
		{
			if ($ht != NULL)
			{
				$query = array(
					'result'   => 'success',
					'jaccount' => $ht['uid'],
					'user_id'  => $ht['id'],
					'name'     => $ht['chinesename']
				);
			}
			else
			{
				$query = array('result' => 'fail');
			}
			$this->jiaccount_redirect($redirect_query['uri'], $query);
		}
		else
		{
			if ($ht != NULL)
			{
				$_SESSION["user_id"] = $ht['id'];
				$_SESSION["username"] = $ht['chinesename'];
			}
			redirect(base_url($redirect_query['uri']));
			
		}
	}
	
	public function logout()
	{
		$uri = $this->input->get('uri');
		if (ENVIRONMENT == 'development')
		{
			if(!(isset($_SESSION["user_id"])&&$_SESSION["user_id"]))
			{
				redirect(base_url($uri));
				exit();
			}
			$_SESSION["user_id"] = '';
			$_SESSION["username"] = '';
			header('Location: ' . $this::JIACCOUNT_URL . '/user/jiaccount?logout=1&url='
			       . urlencode(base_url('user/logout') . '?uri=' . $this->input->get('uri')));
		}
		$auth_type = $this->input->get('auth_type');
		$this->load->library('JAccount');
		$jam = new JAccountManager('jaji20150623', 'jaccount');
		if ($auth_type == 'jiaccount')
		{
			$redirect_uri = ROOT_DIR . '/user/jiaccount_logout?uri=' . urlencode($uri);
			$jam->logout($redirect_uri);
		}
		else
		{
			$_SESSION["user_id"] = '';
			$_SESSION["username"] = '';
			$jam->logout(ROOT_DIR . $this->input->get('uri'));
		}
	}
	
	public function settings()
	{
		$data = $this->data;
		$data['page_name'] = 'Settings';
		$this->load->view('user/settings', $data);
		
	}
}
