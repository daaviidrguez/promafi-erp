<?php

namespace Tests\Unit;

use App\Support\Rendimiento\RendimientoMysqlErrores;
use App\Support\Rendimiento\RendimientoRecolector;
use Illuminate\Database\QueryException;
use PDOException;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class RendimientoRecolectorTest extends TestCase
{
    #[Test]
    public function no_acumula_fuera_de_una_peticion(): void
    {
        $r = new RendimientoRecolector;
        $r->registrarConsulta('select 1', 5000);
        $r->registrarLlamadaExterna('https://api.anthropic.com/v1/messages', 'post', 60000);

        $this->assertSame(0, $r->resumen()['sql_consultas']);
        $this->assertSame([], $r->resumen()['http_externas']);
    }

    #[Test]
    public function resume_sql_y_conserva_la_consulta_mas_lenta(): void
    {
        $r = new RendimientoRecolector;
        $r->iniciar();
        $r->registrarConsulta('select * from a', 20);
        $r->registrarConsulta("select *\n   from b   where id = ?", 1500);
        $r->registrarConsulta('select * from c', 300);
        $r->detener();
        $r->registrarConsulta('select * from ignorada', 9000);

        $resumen = $r->resumen();
        $this->assertSame(3, $resumen['sql_consultas']);
        $this->assertSame(1820.0, $resumen['sql_total_ms']);
        $this->assertSame(1, $resumen['sql_lentas']);
        $this->assertSame(1500.0, $resumen['sql_mas_lenta']['ms']);
        $this->assertSame('select * from b where id = ?', $resumen['sql_mas_lenta']['sql']);
    }

    #[Test]
    public function solo_registra_llamadas_externas_lentas_o_fallidas(): void
    {
        $r = new RendimientoRecolector;
        $r->iniciar();
        $r->registrarLlamadaExterna('https://maps.googleapis.com/x', 'get', 800);
        $r->registrarLlamadaExterna('https://api.facturama.mx/3/cfdis', 'post', 12000);
        $r->registrarLlamadaExterna('https://api.anthropic.com/v1/messages', 'post', null, 'cURL error 28: timed out');

        $externas = $r->resumen()['http_externas'];
        $this->assertCount(2, $externas);
        $this->assertSame(['host' => 'api.facturama.mx', 'metodo' => 'POST', 'ms' => 12000.0, 'error' => null], $externas[0]);
        $this->assertSame('api.anthropic.com', $externas[1]['host']);
        $this->assertNull($externas[1]['ms']);
        $this->assertSame('cURL error 28: timed out', $externas[1]['error']);
    }

    #[Test]
    public function iniciar_reinicia_las_metricas(): void
    {
        $r = new RendimientoRecolector;
        $r->iniciar();
        $r->registrarConsulta('select 1', 2000);
        $r->iniciar();

        $this->assertSame(0, $r->resumen()['sql_consultas']);
        $this->assertNull($r->resumen()['sql_mas_lenta']);
    }

    #[Test]
    public function detecta_codigos_mysql_de_saturacion(): void
    {
        $pdo = new PDOException('SQLSTATE[HY000]: General error: 1205 Lock wait timeout exceeded');
        $pdo->errorInfo = ['HY000', 1205, 'Lock wait timeout exceeded'];
        $query = new QueryException('mysql', 'update x set y = 1', [], $pdo);

        $this->assertSame(1205, RendimientoMysqlErrores::codigoMysql($query));
        $this->assertSame(
            1040,
            RendimientoMysqlErrores::codigoMysql(new PDOException('SQLSTATE[HY000] [1040] Too many connections'))
        );
        $this->assertSame(
            2002,
            RendimientoMysqlErrores::codigoMysql(new RuntimeException('wrap', 0, new PDOException('SQLSTATE[HY000] [2002] Connection refused')))
        );
        $this->assertNull(RendimientoMysqlErrores::codigoMysql(new RuntimeException('otro error')));

        $this->assertTrue(RendimientoMysqlErrores::esSinConexion(new PDOException('SQLSTATE[HY000] [1040] Too many connections')));
        $this->assertFalse(RendimientoMysqlErrores::esSinConexion($query));
    }
}
