<?php
namespace App\Controllers;
use App\Models\ResumeModel;
use CodeIgniter\Exceptions\PageNotFoundException;
class Resume extends BaseController {
 private ResumeModel $model;
 public function __construct(){ $this->model=new ResumeModel(); }
 private function key(string $v): string { return hash('sha256', mb_strtolower(trim($v))); }
 public function index(){ return view('resume/index'); }
 public function builder($size='a4'){ $size=in_array($size,['a4','long'],true)?$size:'a4'; return view('resume/builder',['paperSize'=>$size,'resume'=>null]); }
 public function retrieve(){ return view('resume/retrieve',['error'=>null]); }
 public function find(){
  $name=(string)$this->request->getPost('full_name'); $dob=(string)$this->request->getPost('date_of_birth'); $email=(string)$this->request->getPost('email');
  $row=$this->model->where(['full_name_key'=>$this->key($name),'date_of_birth'=>$dob,'email_key'=>$this->key($email)])->orderBy('updated_at','DESC')->first();
  if(!$row) return view('resume/retrieve',['error'=>'No saved resume matched those details.']);
  $row['data']=json_decode($row['resume_data'],true)?:[]; return view('resume/builder',['paperSize'=>$row['paper_size'],'resume'=>$row]);
 }
 public function save(){
  $rules=['full_name'=>'required|max_length[160]','date_of_birth'=>'required|valid_date[Y-m-d]','email'=>'required|valid_email|max_length[190]','paper_size'=>'required|in_list[a4,long]'];
  if(!$this->validate($rules)) return $this->response->setStatusCode(422)->setJSON(['ok'=>false,'errors'=>$this->validator->getErrors()]);
  $data=$this->request->getPost(); $uuid=(string)($data['uuid']??''); $existing=$uuid?$this->model->where('uuid',$uuid)->first():null;
  $photo=$existing['photo_path']??null; $file=$this->request->getFile('photo');
  if($file && $file->isValid() && !$file->hasMoved()) { if(!in_array($file->getMimeType(),['image/jpeg','image/png','image/webp'],true) || $file->getSize()>3*1024*1024) return $this->response->setStatusCode(422)->setJSON(['ok'=>false,'errors'=>['photo'=>'Photo must be JPG, PNG, or WEBP and at most 3 MB.']]); $new=$file->getRandomName(); $file->move(WRITEPATH.'uploads/resumes',$new); $photo=$new; }
  foreach(['education','achievements','references'] as $k){ if(isset($data[$k]) && is_array($data[$k])) $data[$k]=array_slice($data[$k],0,5); }
  $uuid=$existing['uuid']??sprintf('%s-%s-%s-%s-%s',bin2hex(random_bytes(4)),bin2hex(random_bytes(2)),'4'.substr(bin2hex(random_bytes(2)),1),(dechex(random_int(8,11))).substr(bin2hex(random_bytes(2)),1),bin2hex(random_bytes(6)));
  $record=['uuid'=>$uuid,'paper_size'=>$data['paper_size'],'full_name'=>trim($data['full_name']),'full_name_key'=>$this->key($data['full_name']),'date_of_birth'=>$data['date_of_birth'],'email'=>trim($data['email']),'email_key'=>$this->key($data['email']),'photo_path'=>$photo,'resume_data'=>json_encode($data,JSON_UNESCAPED_UNICODE),'last_generated_at'=>date('Y-m-d H:i:s')];
  if($existing) $this->model->update($existing['id'],$record); else $this->model->insert($record);
  return $this->response->setJSON(['ok'=>true,'uuid'=>$uuid,'preview'=>site_url('resume/preview/'.$uuid),'download'=>site_url('resume/download/'.$uuid)]);
 }
 public function preview($uuid){ $r=$this->loadResume($uuid); return view('resume/pdf',$r); }
 public function download($uuid){
  $r=$this->loadResume($uuid); $html=view('resume/pdf',$r);
  if(!class_exists('Dompdf\\Dompdf')) return $this->response->setStatusCode(500)->setBody('Dompdf is not installed. Run: composer require dompdf/dompdf');
  $dompdf=new \Dompdf\Dompdf(['isRemoteEnabled'=>false]); $dompdf->loadHtml($html); $paper=$r['paperSize']==='long'?[0,0,612,936]:'a4'; $dompdf->setPaper($paper,'portrait'); $dompdf->render();
  return $this->response->setHeader('Content-Type','application/pdf')->setHeader('Content-Disposition','attachment; filename="resume-'.$uuid.'.pdf"')->setBody($dompdf->output());
 }
 private function loadResume($uuid): array { $row=$this->model->where('uuid',$uuid)->first(); if(!$row) throw PageNotFoundException::forPageNotFound(); return ['resume'=>$row,'data'=>json_decode($row['resume_data'],true)?:[],'paperSize'=>$row['paper_size']]; }
}
