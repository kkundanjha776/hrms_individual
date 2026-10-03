<?php
require_once __DIR__.'/core.php';
hrRequireCsrf();
$currentEmployee = hrCurrentEmployee($hrDb);
if ($currentEmployee && strtoupper((string)($currentEmployee['role'] ?? '')) === 'EMPLOYEE') {
    header('Location: employee_leave.php');
    exit;
}
$companyId=hrCompanyId($hrDb);
if($_SERVER['REQUEST_METHOD']==='POST'){
    $action=$_POST['action']??'';
    if($action==='create'){
        $employeeId=(int)($_POST['employee_id']??0);$type=trim($_POST['leave_type']??'');$start=$_POST['start_date']??'';$end=$_POST['end_date']??'';$reason=trim($_POST['reason']??'');
        if(!$employeeId||!$type||!$start||!$end||$end<$start){hrFlash('Choose an employee and valid leave dates.','error');}
        else{$check=$hrDb->prepare('SELECT id FROM hr_employees WHERE id=? AND company_id=?');$check->bind_param('ii',$employeeId,$companyId);$check->execute();if(!$check->get_result()->fetch_assoc()){hrFlash('Invalid employee.','error');}else{$st=$hrDb->prepare('INSERT INTO hr_leave_requests(company_id,employee_id,leave_type,start_date,end_date,reason) VALUES(?,?,?,?,?,?)');$st->bind_param('iissss',$companyId,$employeeId,$type,$start,$end,$reason);$st->execute();$st->close();hrFlash('Leave request created.');}$check->close();}
    } elseif(in_array($action,['approve','reject'],true)) {
        $id=(int)($_POST['request_id']??0);$status=$action==='approve'?'Approved':'Rejected';$st=$hrDb->prepare('UPDATE hr_leave_requests SET status=? WHERE id=? AND company_id=? AND status="Pending"');$st->bind_param('sii',$status,$id,$companyId);$st->execute();$st->close();hrFlash($status==='Approved'?'Leave request approved.':'Leave request rejected.');
    }
    header('Location: leave.php');exit;
}
$employees=$hrDb->query("SELECT id,employee_code,first_name,last_name FROM hr_employees WHERE company_id=$companyId AND status='Active' ORDER BY first_name")->fetch_all(MYSQLI_ASSOC);
$requests=$hrDb->query("SELECT l.*,e.first_name,e.last_name,e.employee_code FROM hr_leave_requests l JOIN hr_employees e ON e.id=l.employee_id WHERE l.company_id=$companyId ORDER BY l.created_at DESC")->fetch_all(MYSQLI_ASSOC);
hrPageStart('Leave requests'); ?>
<div class="page-head"><div><h2>Leave workflow</h2><p>Create, review and approve leave requests for this company.</p></div></div>
<div class="form-card"><h3>New leave request</h3><form method="post"><input type="hidden" name="csrf" value="<?=hrCsrf()?>"><input type="hidden" name="action" value="create"><div class="form-grid"><label>Employee<select name="employee_id" required><option value="">Select employee</option><?php foreach($employees as $e):?><option value="<?=$e['id']?>"><?=htmlspecialchars($e['first_name'].' '.$e['last_name'].' · '.$e['employee_code'])?></option><?php endforeach?></select></label><label>Leave type<select name="leave_type"><option>Annual Leave</option><option>Sick Leave</option><option>Personal Leave</option><option>Unpaid Leave</option></select></label><label>Reason<input name="reason" placeholder="Optional note"></label><label>Start date<input type="date" name="start_date" required></label><label>End date<input type="date" name="end_date" required></label></div><div class="form-actions"><button class="button">Create request</button></div></form></div>
<div class="panel"><div class="table-wrap"><table class="data-table"><thead><tr><th>EMPLOYEE</th><th>TYPE</th><th>DATES</th><th>REASON</th><th>STATUS</th><th>ACTION</th></tr></thead><tbody><?php foreach($requests as $r):?><tr><td><b><?=htmlspecialchars($r['first_name'].' '.$r['last_name'])?></b><br><small><?=htmlspecialchars($r['employee_code'])?></small></td><td><?=htmlspecialchars($r['leave_type'])?></td><td><?=htmlspecialchars($r['start_date'])?> to <?=htmlspecialchars($r['end_date'])?></td><td><?=htmlspecialchars($r['reason'])?></td><td><span class="badge <?=strtolower($r['status'])?>"><?=htmlspecialchars($r['status'])?></span></td><td><?php if($r['status']==='Pending'):?><form method="post" style="display:flex;gap:6px"><input type="hidden" name="csrf" value="<?=hrCsrf()?>"><input type="hidden" name="request_id" value="<?=$r['id']?>"><button class="button" name="action" value="approve">Approve</button><button class="button danger" name="action" value="reject">Reject</button></form><?php else:?>-<?php endif?></td></tr><?php endforeach;if(!$requests):?><tr><td colspan="6" class="empty">No leave requests yet.</td></tr><?php endif?></tbody></table></div></div>
<?php hrPageEnd(); ?>
