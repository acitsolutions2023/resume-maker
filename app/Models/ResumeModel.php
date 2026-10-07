<?php
namespace App\Models;
use CodeIgniter\Model;
class ResumeModel extends Model {
 protected $table='resumes'; protected $primaryKey='id'; protected $returnType='array';
 protected $allowedFields=['uuid','paper_size','full_name','full_name_key','date_of_birth','email','email_key','photo_path','resume_data','last_generated_at'];
 protected $useTimestamps=true;
}
