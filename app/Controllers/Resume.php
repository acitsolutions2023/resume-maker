<?php
namespace App\Controllers;
use App\Libraries\Photo;
use App\Libraries\ResumeDocx;
use App\Models\ResumeModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use Throwable;
class Resume extends BaseController {
 private const TEXT_FIELDS=['paper_size'=>10,'full_name'=>160,'objective'=>800,'street'=>160,'location'=>160,'contact'=>40,'email'=>190,'date_of_birth'=>10,'citizenship'=>60,'sex'=>30,'civil_status'=>40,'height'=>40,'weight'=>40,'languages'=>300];
 private ResumeModel $model;
 public function __construct(){ $this->model=new ResumeModel(); }
 private function key(string $v): string { return hash('sha256', mb_strtolower(trim($v))); }
 public function index(){ return view('resume/index'); }
 public function builder($size='a4'){ $size=in_array($size,['a4','long'],true)?$size:'a4'; return view('resume/builder',['paperSize'=>$size,'resume'=>null]); }
 public function retrieve(){ return view('resume/retrieve',['error'=>null]); }
 public function find(){
  $name=(string)$this->request->getPost('full_name'); $dob=(string)$this->request->getPost('date_of_birth'); $email=(string)$this->request->getPost('email');
  $row=$this->model->where(['full_name_key'=>$this->key($name),'date_of_birth'=>$dob,'email_key'=>$this->key($email)])->orderBy('updated_at','DESC')->first();
  if(!$row) return view('resume/retrieve',['error'=>'No saved resume matched those details. Check the spelling of your name, your date of birth, and your email.']);
  $row['data']=json_decode($row['resume_data'],true)?:[]; return view('resume/builder',['paperSize'=>$row['paper_size'],'resume'=>$row]);
 }

 /** Upload endpoint: crops the photo to a 2x2 square and returns its stored name. */
 public function photo(){
  $file=$this->request->getFile('photo');
  if(!$file || !$file->isValid()) return $this->response->setStatusCode(422)->setJSON(['ok'=>false,'errors'=>['photo'=>$file?$file->getErrorString():'Please choose a photo to upload.']]);
  if(!in_array($file->getMimeType(),Photo::MIMES,true)) return $this->response->setStatusCode(422)->setJSON(['ok'=>false,'errors'=>['photo'=>'Photo must be a JPG, PNG, or WEBP image.']]);
  if($file->getSize()>Photo::MAX_BYTES) return $this->response->setStatusCode(422)->setJSON(['ok'=>false,'errors'=>['photo'=>'Photo is too large. Maximum size is 8 MB.']]);
  try { $name=Photo::store($file->getTempName()); }
  catch(Throwable $e){ return $this->response->setStatusCode(422)->setJSON(['ok'=>false,'errors'=>['photo'=>'We could not read that image. Try another photo.']]); }
  return $this->response->setJSON(['ok'=>true,'photo'=>$name]);
 }

 /** Live preview: renders the posted (unsaved) form data. */
 public function render(){
  $post=$this->request->getPost(); $data=$this->clean($post); $token=(string)($post['photo_token']??''); $photo=null;
  if(empty($post['photo_remove'])){
   if(Photo::path($token)) $photo=basename($token);
   elseif(!empty($post['uuid'])) $photo=$this->model->select('photo_path')->where('uuid',(string)$post['uuid'])->first()['photo_path']??null;
  }
  return view('resume/pdf',['resume'=>['photo_path'=>$photo],'data'=>$data,'paperSize'=>$data['paper_size']]);
 }

 public function save(){
  $post=$this->request->getPost(); $data=$this->clean($post);
  $errors=$this->validateResume($data);
  $uuid=(string)($post['uuid']??''); $existing=$uuid?$this->model->where('uuid',$uuid)->first():null;
  $photo=$existing['photo_path']??null; $token=(string)($post['photo_token']??'');
  if(!empty($post['photo_remove'])) $photo=null; elseif($token!=='' && Photo::path($token)) $photo=basename($token);
  if(!Photo::path($photo)) $errors['photo']='A 2×2 photo is required.';
  if($errors) return $this->response->setStatusCode(422)->setJSON(['ok'=>false,'errors'=>$errors]);
  $uuid=$existing['uuid']??sprintf('%s-%s-%s-%s-%s',bin2hex(random_bytes(4)),bin2hex(random_bytes(2)),'4'.substr(bin2hex(random_bytes(2)),1),(dechex(random_int(8,11))).substr(bin2hex(random_bytes(2)),1),bin2hex(random_bytes(6)));
  $record=['uuid'=>$uuid,'paper_size'=>$data['paper_size'],'full_name'=>$data['full_name'],'full_name_key'=>$this->key($data['full_name']),'date_of_birth'=>$data['date_of_birth'],'email'=>$data['email'],'email_key'=>$this->key($data['email']),'photo_path'=>$photo,'resume_data'=>json_encode($data,JSON_UNESCAPED_UNICODE),'last_generated_at'=>date('Y-m-d H:i:s')];
  if($existing) $this->model->update($existing['id'],$record); else $this->model->insert($record);
  return $this->response->setJSON(['ok'=>true,'uuid'=>$uuid,'preview'=>site_url('resume/preview/'.$uuid),'download'=>site_url('resume/download/'.$uuid),'pdf'=>site_url('resume/pdf/'.$uuid)]);
 }
 public function preview($uuid){ $r=$this->loadResume($uuid); return view('resume/pdf',$r); }

 /** Generates the resume as a Word document from the DOCX template. */
 public function download($uuid){
  $r=$this->loadResume($uuid);
  $docx=(new ResumeDocx($r['data'],$r['paperSize'],Photo::path($r['resume']['photo_path']??null)))->render();
  $name=trim(preg_replace('/[^\p{L}\p{N} ._-]+/u','',(string)($r['data']['full_name']??'')))?:'resume';
  return $this->response->setHeader('Content-Type','application/vnd.openxmlformats-officedocument.wordprocessingml.document')->setHeader('Content-Disposition','attachment; filename="Resume - '.$name.'.docx"; filename*=UTF-8\'\''.rawurlencode('Resume - '.$name.'.docx'))->setBody($docx);
 }
 public function pdf($uuid){
  $r=$this->loadResume($uuid); $html=view('resume/pdf',$r);
  if(!class_exists('Dompdf\\Dompdf')) return $this->response->setStatusCode(500)->setBody('Dompdf is not installed. Run: composer require dompdf/dompdf');
  $dompdf=new \Dompdf\Dompdf(['isRemoteEnabled'=>false]); $dompdf->loadHtml($html); $paper=$r['paperSize']==='long'?[0,0,612,936]:'a4'; $dompdf->setPaper($paper,'portrait'); $dompdf->render();
  return $this->response->setHeader('Content-Type','application/pdf')->setHeader('Content-Disposition','attachment; filename="resume-'.$uuid.'.pdf"')->setBody($dompdf->output());
 }
 private function loadResume($uuid): array { $row=$this->model->where('uuid',$uuid)->first(); if(!$row) throw PageNotFoundException::forPageNotFound(); return ['resume'=>$row,'data'=>json_decode($row['resume_data'],true)?:[],'paperSize'=>$row['paper_size']]; }

 /** Keeps only known fields, trims values and drops empty line items. */
 private function clean(array $post): array {
  $str=static fn($v,int $max)=>is_string($v)?mb_substr(trim($v),0,$max):'';
  $d=[]; foreach(self::TEXT_FIELDS as $k=>$max) $d[$k]=$str($post[$k]??'',$max);
  $d['paper_size']=$d['paper_size']==='long'?'long':'a4';
  foreach(['skills','achievements'] as $k){ $d[$k]=array_slice(array_values(array_filter(array_map(fn($v)=>$str($v,200),is_array($post[$k]??null)?$post[$k]:[]),'strlen')),0,5); }
  $rows=['education'=>['level','school','address','years'],'references'=>['name','contact','position','organization']];
  foreach($rows as $k=>$fields){ $d[$k]=[]; foreach(is_array($post[$k]??null)?$post[$k]:[] as $row){ if(!is_array($row)) continue; $r=[]; foreach($fields as $f) $r[$f]=$str($row[$f]??'',160); if(implode('',$r)!=='') $d[$k][]=$r; } $d[$k]=array_slice($d[$k],0,5); }
  return $d;
 }

 /** Same rules as the inline checks in resume-maker.js, with matching messages. */
 private function validateResume(array $d): array {
  $e=[];
  if($d['full_name']==='') $e['full_name']='Full name is required.';
  elseif(mb_strlen($d['full_name'])<2 || !preg_match("/^[\p{L}\p{M}][\p{L}\p{M} .,'-]*$/u",$d['full_name'])) $e['full_name']='Use letters only, e.g. Juan Dela Cruz.';
  if($d['email']==='') $e['email']='Email is required.';
  elseif(!filter_var($d['email'],FILTER_VALIDATE_EMAIL)) $e['email']='Enter a valid email, e.g. juandelacruz@gmail.com.';
  $dob=\DateTime::createFromFormat('!Y-m-d',$d['date_of_birth']);
  if($d['date_of_birth']==='') $e['date_of_birth']='Date of birth is required.';
  elseif(!$dob || $dob->format('Y-m-d')!==$d['date_of_birth']) $e['date_of_birth']='Enter a valid date of birth.';
  elseif($dob>new \DateTime('today')) $e['date_of_birth']='Date of birth cannot be in the future.';
  elseif((int)$dob->format('Y')<1900) $e['date_of_birth']='Please check the year of your date of birth.';
  $phone="/^(\+?63|0)9\d{2}[\s-]?\d{3}[\s-]?\d{4}$|^\(?0\d{1,2}\)?[\s-]?\d{3,4}[\s-]?\d{4}$/";
  $required=['street'=>'Street / Barangay','location'=>'Town / Province','contact'=>'Contact number','citizenship'=>'Citizenship','sex'=>'Sex','civil_status'=>'Civil status','height'=>'Height','weight'=>'Weight','languages'=>'Languages / Dialects'];
  foreach($required as $k=>$label) if($d[$k]==='') $e[$k]=$label.' is required.';
  $rowLabels=['education'=>['level'=>'Level / Course','school'=>'School','address'=>'School address','years'=>'School year'],'references'=>['name'=>'Name','contact'=>'Contact number','position'=>'Position','organization'=>'School / Company / Organization']];
  foreach($rowLabels as $k=>$labels){
   if(!$d[$k]) { $e[$k]=($k==='education'?'Add at least one educational attainment.':'Add at least one character reference.'); continue; }
   foreach($d[$k] as $i=>$row) foreach($labels as $f=>$label) if($row[$f]==='') $e["{$k}[$i][$f]"]=$label.' is required.';
  }
  if($d['contact']!=='' && !preg_match($phone,$d['contact'])) $e['contact']='Use a PH number, e.g. 0926-000-0000 or +63 926 000 0000.';
  foreach($d['references'] as $i=>$r){ if($r['contact']!=='' && !preg_match($phone,$r['contact'])) $e["references[$i][contact]"]='Use a PH number, e.g. 0992-000-0000.'; }
  if(mb_strlen($d['objective'])>800) $e['objective']='Objective is too long (max 800 characters).';
  return $e;
 }
}
