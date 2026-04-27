Usuario: usr_alu_web
Contraseña: @Sys-un3@W3B#2025
Puerto: 5432
Servidor =10.10.10.2;
Base de datos: une_base
function login(){
    $user = $_REQUEST['user'];
    $pass= $_REQUEST['pass'];
    if(getenv($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $xip = getenv($_SERVER['HTTP_X_FORWARDED_FOR']); }
        elseif(getenv($_SERVER['HTTP_CLIENT_IP']))
            { $xip = getenv($_SERVER['HTTP_CLIENT_IP']); }
        else { $xip = $_SERVER['REMOTE_ADDR']; }
    $return = db_sql2("SELECT * From  fn_consultor_verificacion_pin_web2('$user', '$pass','$xip')");
    if(isset($return->ERROR)){
        $return->logged = false;
    }else{

        $return->logged = true;
        $_SESSION['user_logado'] = $return;

    }
    json_enc($return);
}
Fn_busca_alumnos_une()

function m_carreras(){
    $alu =$_SESSION['user_logado'];
    $return = db_sql("select * from vw_alumnos_habilitacion_22
                where vw_alumnos_habilitacion_22.alu_id='{$alu['alu_id']}';");

    json_enc($return);
    
}


    $query = "SELECT * FROM fn_busca_alumnos_habilitacion_extracto($alu_hal_id,0,1);";

SELECT uac_descri, ciu_descri, cob_numero, cob_fecha, cob_monto, cob_arancel,
       cob_perceptor, mat_descri, cob_idrol, cob_tprol,year(cob_fecha) as anho FROM fn_consultor_alumnos_pagos($alu_id,'A',null,null);";

SELECT mat_descri, cur_descri, tur_descri, sec_descri, ahf_ptotal,ple_codigo,ple_descri from fn_consultor_habilitaciones_vigentes($hal_id),
    vw_periodo_lectivo_11 where ple_id=ahf_idple ORDER BY cur_descri ASC;";

SELECT *, sp_alumnos_calcula_asistencias_bonificaciones(alu_clase,alu_presen) as alu_bonif FROM vw_asistencia_alumnos_14
     WHERE aal_idrsc =$alu_rsc_id   AND aai_idalu =$alu_alu_id;

$query = "SELECT mat_descri,sec_descri,tur_descri,inm_fecha,cur_descri,ple_codigo FROM vw_alumnos_inscriptos_materias_14
     WHERE inm_idrsc =$alu_rsc_id   AND alu_id =$alu_alu_id;";


    $query = "SELECT * FROM  fn_consultor_evaluaciones_parciales($hal_id);";


    $query = "SELECT * FROM  fn_consultor_inscripciones_finales($hal_id);";


    $query = "SELECT * FROM  fn_consultor_evaluaciones_finales($hal_id);";

    $query = "SELECT * FROM fn_consultor_alumnos_deudas($alu_id)  ORDER BY dit_vencim DESC;";

    $return = db_sql("select * from fn_consultor_promedio($hal_id,0,0) as promedio;");


    $return2 = db_sql("select * from vw_actividades_00 where act_vigent=TRUE;");
        $return = db_sql2("SELECT * FROM  fn_psw_change('$pass1','$pass2','{$alu_id['alu_id']}')");

Puedes priorizar estas vistas 