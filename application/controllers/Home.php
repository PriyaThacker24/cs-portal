@ -0,0 +1,11 @@
<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Home extends CI_Controller
{
    public function index()
    {
        redirect('admin');
    }
}