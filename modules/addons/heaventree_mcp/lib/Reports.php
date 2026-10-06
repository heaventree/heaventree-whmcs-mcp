<?php
declare(strict_types=1);
namespace Heaventree\WhmcsMcp;

final class Reports
{
    public static function tools(): array
    {
        $out=[];
        foreach (['mrr','churn','revenue','top_clients','invoice_aging','zero_invoices','billing_gaps','plan_audit','domain_renewals','cost_breakdown'] as $report) {
            $out[]=['name'=>'whmcs_bi_'.$report,'scope'=>'read','report'=>$report,'description'=>'Read-only '.$report.' analysis. Returns per-currency results, coverage and source timestamps. WHMCS dates are not independent registrar verification.','inputSchema'=>['type'=>'object','properties'=>['limit'=>['type'=>'integer','minimum'=>1,'maximum'=>200,'default'=>50],'offset'=>['type'=>'integer','minimum'=>0,'maximum'=>1000000,'default'=>0],'since'=>['type'=>'string','pattern'=>'^20[0-9]{2}-[0-9]{2}-[0-9]{2}$'],'as_of'=>['type'=>'string','pattern'=>'^20[0-9]{2}-[0-9]{2}-[0-9]{2}$'],'days'=>['type'=>'integer','minimum'=>1,'maximum'=>365,'default'=>30],'plans'=>['type'=>'array','items'=>['type'=>'string']]],'required'=>[],'additionalProperties'=>false],'annotations'=>['readOnlyHint'=>true,'destructiveHint'=>false,'idempotentHint'=>true,'openWorldHint'=>false]];
        }
        return $out;
    }
    public static function money(string $value): int
    {
        if (!preg_match('/^(-?)([0-9]{1,12})(?:\.([0-9]{1,2}))?$/D',$value,$m)) throw new McpException('Unsupported monetary precision. This release reports two-decimal WHMCS amounts only.');
        return ($m[1]==='-'?-1:1)*((int)$m[2]*100+(int)str_pad($m[3]??'',2,'0'));
    }
    public static function decimal(int $minor): string { return ($minor<0?'-':'').intdiv(abs($minor),100).'.'.str_pad((string)(abs($minor)%100),2,'0',STR_PAD_LEFT); }
    public static function mrr(array $rows): array
    {
        $cycles=['Monthly'=>1,'Quarterly'=>3,'Semi-Annually'=>6,'Annually'=>12,'Biennially'=>24,'Triennially'=>36]; $groups=[]; $excluded=0;
        foreach ($rows as $row) {
            if (($row['domainstatus']??'')!=='Active'||!isset($cycles[$row['billingcycle']??''])) { $excluded++; continue; }
            $currency=(string)$row['currency']; $minor=self::money((string)$row['amount']);
            $groups[$currency]['normalized_minor']=($groups[$currency]['normalized_minor']??0)+$minor/$cycles[$row['billingcycle']];
            $groups[$currency]['services']=($groups[$currency]['services']??0)+1;
        }
        foreach ($groups as &$group) { $group['estimated_mrr']=self::decimal((int)round($group['normalized_minor'])); unset($group['normalized_minor']); }
        return ['byCurrency'=>$groups,'excludedNonRecurringOrInactive'=>$excluded,'basis'=>'Expected active service recurring revenue, not collected cash; excludes domains and addons. Normalization rounded once per currency.'];
    }
    public static function run(string $name,array $args): array
    {
        $capsule='\\WHMCS\\Database\\Capsule'; $limit=$args['limit']??50; $offset=$args['offset']??0;
        $since=$args['since']??date('Y-01-01'); $asOf=$args['as_of']??date('Y-m-d');
        foreach ([$since,$asOf] as $date) { $parsed=\DateTimeImmutable::createFromFormat('!Y-m-d',$date); if (!$parsed||$parsed->format('Y-m-d')!==$date) throw new McpException('Invalid calendar date.'); }
        if ($since>$asOf) throw new McpException('since must not be after as_of.');
        $base=['sourceAsOf'=>gmdate('c'),'dateTimezone'=>date_default_timezone_get(),'source'=>'WHMCS local database','currencyPrecision'=>2,'coverage'=>'bounded','readOnly'=>true];
        if ($name==='mrr') {
            $rows=$capsule::table('tblhosting as h')->join('tblclients as c','c.id','=','h.userid')->join('tblcurrencies as k','k.id','=','c.currency')->where('h.domainstatus','Active')->orderBy('h.id','desc')->limit(10001)->get(['h.id','h.amount','h.billingcycle','h.domainstatus','k.code as currency'])->map(fn($r)=>(array)$r)->all();
            $complete=count($rows)<=10000;
            unset($base['coverage']);
            return $base+self::mrr(array_slice($rows,0,10000))+['coverage'=>$complete?'complete_active_service_snapshot':'partial_first_10000_services','complete'=>$complete];
        }
        if ($name==='domain_renewals') {
            $q=$capsule::table('tbldomains')->whereIn('status',['Active','Expired','Grace','Redemption'])->where('expirydate','<=',date('Y-m-d',strtotime($asOf.' +'.($args['days']??30).' days')))->where('expirydate','>','0000-00-00')->orderBy('expirydate')->orderBy('id','desc');
            $fields=['id','userid','domain','status','registrar','expirydate','nextduedate','donotrenew','registrationperiod'];
            $base+=['dateSemantics'=>'WHMCS recorded expiry only','registrarLiveExpiryVerified'=>false,'renewalPaymentVerified'=>false,'asOf'=>$asOf];
        } elseif ($name==='plan_audit') {
            $plans=$args['plans']??['Webcore','Webpro','Webstore'];
            if (count($plans)>30||array_filter($plans,fn($p)=>!is_string($p)||strlen($p)>100)) throw new McpException('Invalid plan list.');
            $q=$capsule::table('tblhosting as h')->join('tblclients as c','c.id','=','h.userid')->leftJoin('tblproducts as p','p.id','=','h.packageid')->where('h.domainstatus','Active')->where(function($q)use($plans){$q->whereNull('p.name')->orWhereNotIn('p.name',$plans);})->orderBy('h.id','desc');
            $fields=['h.id','h.userid','h.domain','h.packageid','p.name as plan','h.billingcycle','h.amount','h.nextduedate','c.currency']; $base['requiresReview']='Exact plan names only. Discounted variants and intentional/internal services require owner classification.';
        } elseif ($name==='billing_gaps') {
            $q=$capsule::table('tblhosting as h')->join('tblclients as c','c.id','=','h.userid')->where('h.domainstatus','Active')->where('c.status','Active')->whereNotIn('h.billingcycle',['Free Account','One Time'])->whereNotExists(function($q)use($since,$asOf){$q->selectRaw('1')->from('tblinvoiceitems as ii')->join('tblinvoices as i','i.id','=','ii.invoiceid')->whereColumn('ii.relid','h.id')->where('ii.type','Hosting')->whereBetween('i.date',[$since,$asOf])->where('i.status','!=','Cancelled');})->orderBy('h.id','desc');
            $fields=['h.id','h.userid','h.domain','h.packageid','h.billingcycle','h.amount','h.nextduedate','c.currency']; $base+=['since'=>$since,'asOf'=>$asOf,'requiresReview'=>'Absence of a non-cancelled Hosting invoice in this window; annual cycles, credits, deferrals and internal services can be legitimate. Not automatically missing revenue.'];
        } elseif (in_array($name,['invoice_aging','zero_invoices'],true)) {
            $q=$capsule::table('tblinvoices as i')->join('tblclients as c','c.id','=','i.userid')->join('tblcurrencies as k','k.id','=','c.currency');
            if ($name==='zero_invoices') $q->where('i.total','0')->whereBetween('i.date',[$since,$asOf]);
            else $q->where('i.status','Unpaid')->where('i.duedate','<',$asOf);
            $q->orderBy('i.date','desc')->orderBy('i.id','desc'); $fields=['i.id','i.userid','i.date','i.duedate','i.status','i.subtotal','i.credit','i.total','k.code as currency'];
            $base['requiresReview']='Zero totals may reflect credits, free services or adjustments. Unpaid invoices are exposure, not proven failed payment collection.';
        } elseif (in_array($name,['revenue','top_clients','cost_breakdown'],true)) {
            $q=$capsule::table('tblaccounts as a')->join('tblclients as c','c.id','=','a.userid')->join('tblcurrencies as k','k.id','=','c.currency')->whereBetween('a.date',[$since.' 00:00:00',$asOf.' 23:59:59'])->groupBy('k.code');
            $fields=['k.code as currency'];
            if ($name==='top_clients') { $q->groupBy('a.userid');$fields[]='a.userid'; }
            $q->select($fields)->selectRaw('SUM(a.amountin) as collected, SUM(a.amountout) as refunds_or_outgoing, SUM(a.fees) as transaction_fees')->orderByRaw('SUM(a.amountin) DESC'); $fields=['*'];
            $base+=['since'=>$since,'asOf'=>$asOf,'basis'=>'Recorded WHMCS ledger transactions, grouped by client currency. No bank settlement verification or inferred hosting/server purchase costs.','currencyWarning'=>'Historical client currency changes and unassigned ledger transactions require reconciliation.'];
        } elseif ($name==='churn') {
            $q=$capsule::table('tblhosting')->whereIn('domainstatus',['Cancelled','Terminated'])->whereBetween('termination_date',[$since,$asOf])->orderBy('termination_date','desc')->orderBy('id','desc');
            $fields=['id','userid','packageid','billingcycle','amount','domainstatus','termination_date']; $base['basis']='Recorded service exits in window. No cohort denominator; this is not a computed churn percentage.';
        } else throw new McpException('Unknown report.');
        $rows=$q->offset($offset)->limit($limit+1)->get($fields)->map(fn($r)=>(array)$r)->all(); $more=count($rows)>$limit;
        return $base+['items'=>array_slice($rows,0,$limit),'offset'=>$offset,'limit'=>$limit,'hasMore'=>$more,'nextOffset'=>$more?$offset+$limit:null];
    }
}
