@if($empresa->banco)
<hr style="margin-top:40px;border:0;border-top:2px solid #0B3C5D;">

<div style="border:1px solid #93C5FD;padding:10px;margin-top:10px;font-size:8pt;">
<strong>DATOS PARA TRANSFERENCIA BANCARIA</strong><br><br>

Banco: {{ $empresa->banco }}<br>
Cuenta: {{ $empresa->numero_cuenta }}<br>
CLABE: {{ $empresa->clabe }}
</div>
@endif