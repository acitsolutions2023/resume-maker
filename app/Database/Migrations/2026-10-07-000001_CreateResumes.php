<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;
class CreateResumes extends Migration {
 public function up(){
  $this->forge->addField([
   'id'=>['type'=>'BIGINT','unsigned'=>true,'auto_increment'=>true],
   'uuid'=>['type'=>'CHAR','constraint'=>36],
   'paper_size'=>['type'=>'VARCHAR','constraint'=>10,'default'=>'a4'],
   'full_name'=>['type'=>'VARCHAR','constraint'=>160],
   'full_name_key'=>['type'=>'CHAR','constraint'=>64],
   'date_of_birth'=>['type'=>'DATE'],
   'email'=>['type'=>'VARCHAR','constraint'=>190],
   'email_key'=>['type'=>'CHAR','constraint'=>64],
   'photo_path'=>['type'=>'VARCHAR','constraint'=>255,'null'=>true],
   'resume_data'=>['type'=>'LONGTEXT'],
   'created_at'=>['type'=>'DATETIME','null'=>true],
   'updated_at'=>['type'=>'DATETIME','null'=>true],
   'last_generated_at'=>['type'=>'DATETIME','null'=>true],
  ]);
  $this->forge->addKey('id',true); $this->forge->addUniqueKey('uuid');
  $this->forge->addKey(['full_name_key','date_of_birth','email_key']);
  $this->forge->createTable('resumes',true);
 }
 public function down(){ $this->forge->dropTable('resumes',true); }
}
