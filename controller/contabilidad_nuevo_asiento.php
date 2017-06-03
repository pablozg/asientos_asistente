<?php
/*
 * This file is part of facturacion_base
 * Copyright (C) 2014-2017  Carlos Garcia Gomez  neorazorx@gmail.com
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 * 
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

require_model('asiento.php');
require_model('concepto_partida.php');
require_model('cuenta_banco.php');
require_model('divisa.php');
require_model('ejercicio.php');
require_model('impuesto.php');
require_model('partida.php');
require_model('regularizacion_iva.php');
require_model('subcuenta.php');

require_model('proveedor.php');
require_model('cuenta.php');
require_model('agente.php');

class contabilidad_nuevo_asiento extends fs_controller
{
   public $asiento;
   public $concepto;
   public $cuenta_banco;
   public $divisa;
   public $ejercicio;
   public $impuesto;
   public $lineas;
   public $resultados;
   public $subcuenta;
   
   public $proveedor;
   public $cuenta;
   public $agente;
   
   public function __construct()
   {
      parent::__construct(__CLASS__, 'Nuevo asiento', 'contabilidad', FALSE, FALSE, TRUE);
   }
   
   protected function private_core()
   {
      $this->ppage = $this->page->get('contabilidad_asientos');
      
      $this->asiento = new asiento();
      $this->concepto = new concepto_partida();
      $this->cuenta_banco = new cuenta_banco();
      $this->divisa = new divisa();
      $this->ejercicio = new ejercicio();
      $this->impuesto = new impuesto();
      $this->lineas = array();
      $this->resultados = array();
      $this->subcuenta = new subcuenta();
      
      $this->proveedor = new proveedor();
      $this->cuenta = new cuenta();
      $this->agente = new agente();
      
      if( isset($_POST['fecha']) AND isset($_POST['query']) )
      {
         $this->new_search();
      }
      else if( isset($_POST['fecha']) AND isset($_POST['concepto']) AND isset($_POST['divisa']) )
      {
         if($_POST['autonomo'] != '0')
         {
            if( floatval($_POST['autonomo']) > 0 )
            {
               $this->nuevo_asiento_autonomo();
            }
            else
            {
               $this->new_error_msg('Importe no válido: '.$_POST['autonomo']);
            }
         }
         else if($_POST['modelo130'] != '0')
         {
            if( floatval($_POST['modelo130']) > 0 )
            {
               $this->nuevo_asiento_modelo130();
            }
            else
            {
               $this->new_error_msg('Importe no válido: '.$_POST['modelo130']);
            }
         }
         else if($_POST['traspasos'] != '0')
         {
            if( floatval($_POST['traspasos']) > 0 )
            {
               $this->nuevo_asiento_traspasos();
            }
            else
            {
               $this->new_error_msg('Importe no válido: '.$_POST['traspasos']);
            }
         }
         else if($_POST['anticipos'] != '0')
         {
            if( floatval($_POST['anticipos']) > 0 )
            {
               $this->nuevo_asiento_anticipos();
            }
            else
            {
               $this->new_error_msg('Importe no válido: '.$_POST['anticipos']);
            }
         }
         else if($_POST['sal_bruto_ajena'] != '0' or $_POST['retenciones_ajena'] != '0' or $_POST['cuota_patronal'] != '0' or $_POST['cuota_obrera'] != '0')
         {
            
            if( floatval($_POST['sal_bruto_ajena']) <= 0 )
            {
            	 $this->new_error_msg('Salario bruto no válido: '.$_POST['sal_bruto_ajena']);
            }
            else if( floatval($_POST['retenciones_ajena']) <= 0 )
            {
            	 $this->new_error_msg('Porcentaje retenciones no válido: '.$_POST['retenciones_ajena']);
          	}
          	else if( floatval($_POST['cuota_patronal']) <= 0 )
            {
            	 $this->new_error_msg('Cuota patronal no válida: '.$_POST['cuota_patronal']);
          	}
          	else if( floatval($_POST['cuota_obrera']) <= 0 )
            {
            	 $this->new_error_msg('Cuota obrera no válida: '.$_POST['cuota_obrera']);
          	}
            else
            {
               $this->nuevo_asiento_nomina_ajena();
            }
         	}
         	else if($_POST['importe_alquiler'] != '0' or $_POST['retenciones_alquiler'] != '0')
         {
            
            if( floatval($_POST['importe_alquiler']) <= 0 )
            {
            	 $this->new_error_msg('Importe del alquiler no válido: '.$_POST['importe_alquiler']);
            }
          	else if( floatval($_POST['retenciones_alquiler']) <= 0 )
            {
            	 $this->new_error_msg('Rentención (%) no válido: '.$_POST['retenciones_alquiler']);
          	}
            else
            {
               $this->nuevo_asiento_alquiler();
            }
         	}
          else
	        {
	            $this->nuevo_asiento();
	        }
      }
      else if( isset($_GET['copy']) )
      {
         $this->copiar_asiento();
      }
      else
      {
         $this->check_datos_contables();
      }
   }
   
   private function get_ejercicio($fecha)
   {
      $ejercicio = FALSE;
      
      $ejercicio = $this->ejercicio->get_by_fecha($fecha);
      if($ejercicio)
      {
         $regiva0 = new regularizacion_iva();
         if( $regiva0->get_fecha_inside($fecha) )
         {
            $this->new_error_msg('No se puede usar la fecha '.$_POST['fecha'].' porque ya hay'
                    . ' una regularización de '.FS_IVA.' para ese periodo.');
            $ejercicio = FALSE;
         }
      }
      else
      {
         $this->new_error_msg('Ejercicio no encontrado.');
      }
      
      return $ejercicio;
   }
   
   private function nuevo_asiento()
   {
      $continuar = TRUE;
      
      $eje0 = $this->get_ejercicio($_POST['fecha']);
      if(!$eje0)
      {
         $continuar = FALSE;
      }
      
      $div0 = $this->divisa->get($_POST['divisa']);
      if(!$div0)
      {
         $this->new_error_msg('Divisa no encontrada.');
         $continuar = FALSE;
      }
      
      if( $this->duplicated_petition($_POST['petition_id']) )
      {
         $this->new_error_msg('Petición duplicada. Has hecho doble clic sobre el botón Guardar
               y se han enviado dos peticiones. Mira en <a href="'.$this->ppage->url().'">asientos</a>
               para ver si el asiento se ha guardado correctamente.');
         $continuar = FALSE;
      }
      
      if($continuar)
      {
         $this->asiento->codejercicio = $eje0->codejercicio;
         $this->asiento->idconcepto = $_POST['idconceptopar'];
         $this->asiento->concepto = $_POST['concepto'];
         $this->asiento->fecha = $_POST['fecha'];
         $this->asiento->importe = floatval($_POST['importe']);
         
         if( $this->asiento->save() )
         {
            $numlineas = intval($_POST['numlineas']);
            for($i=1; $i <= $numlineas; $i++)
            {
               if( isset($_POST['codsubcuenta_'.$i]) )
               {
                  if( $_POST['codsubcuenta_'.$i] != '' AND $continuar)
                  {
                     $sub0 = $this->subcuenta->get_by_codigo($_POST['codsubcuenta_'.$i], $eje0->codejercicio);
                     if($sub0)
                     {
                        $partida = new partida();
                        $partida->idasiento = $this->asiento->idasiento;
                        $partida->coddivisa = $div0->coddivisa;
                        $partida->tasaconv = $div0->tasaconv;
                        $partida->idsubcuenta = $sub0->idsubcuenta;
                        $partida->codsubcuenta = $sub0->codsubcuenta;
                        $partida->debe = floatval($_POST['debe_'.$i]);
                        $partida->haber = floatval($_POST['haber_'.$i]);
                        $partida->idconcepto = $this->asiento->idconcepto;
                        $partida->concepto = $this->asiento->concepto;
                        $partida->documento = $this->asiento->documento;
                        $partida->tipodocumento = $this->asiento->tipodocumento;
                        
                        if( isset($_POST['codcontrapartida_'.$i]) )
                        {
                           if( $_POST['codcontrapartida_'.$i] != '')
                           {
                              $subc1 = $this->subcuenta->get_by_codigo($_POST['codcontrapartida_'.$i], $eje0->codejercicio);
                              if($subc1)
                              {
                                 $partida->idcontrapartida = $subc1->idsubcuenta;
                                 $partida->codcontrapartida = $subc1->codsubcuenta;
                                 $partida->cifnif = $_POST['cifnif_'.$i];
                                 $partida->iva = floatval($_POST['iva_'.$i]);
                                 $partida->baseimponible = floatval($_POST['baseimp_'.$i]);
                              }
                              else
                              {
                                 $this->new_error_msg('Subcuenta '.$_POST['codcontrapartida_'.$i].' no encontrada.');
                                 $continuar = FALSE;
                              }
                           }
                        }
                        
                        if( !$partida->save() )
                        {
                           $this->new_error_msg('Imposible guardar la partida de la subcuenta '.$_POST['codsubcuenta_'.$i].'.');
                           $continuar = FALSE;
                        }
                     }
                     else
                     {
                        $this->new_error_msg('Subcuenta '.$_POST['codsubcuenta_'.$i].' no encontrada.');
                        $continuar = FALSE;
                     }
                  }
               }
            }
            
            if($continuar)
            {
               $this->asiento->concepto = '';
               
               $this->new_message("<a href='".$this->asiento->url()."'>Asiento</a> guardado correctamente!");
               $this->new_change('Asiento '.$this->asiento->numero, $this->asiento->url(), TRUE);
               
               if($_POST['redir'] == 'TRUE')
               {
                  header('Location: '.$this->asiento->url());
               }
            }
            else
            {
               if( $this->asiento->delete() )
               {
                  $this->new_error_msg("¡Error en alguna de las partidas! Se ha borrado el asiento.");
               }
               else
                  $this->new_error_msg("¡Error en alguna de las partidas! Además ha sido imposible borrar el asiento.");
            }
         }
         else
         {
            $this->new_error_msg("¡Imposible guardar el asiento!");
         }
      }
   }
   
   private function nuevo_asiento_autonomo()
   {
      $continuar = TRUE;
      
      $eje0 = $this->get_ejercicio($_POST['fecha']);
      if(!$eje0)
      {
         $continuar = FALSE;
      }
      
      $div0 = $this->divisa->get($_POST['divisa']);
      if(!$div0)
      {
         $this->new_error_msg('Divisa no encontrada.');
         $continuar = FALSE;
      }
      
      if( $this->duplicated_petition($_POST['petition_id']) )
      {
         $this->new_error_msg('Petición duplicada. Has hecho doble clic sobre el botón Guardar
               y se han enviado dos peticiones. Mira en <a href="'.$this->ppage->url().'">asientos</a>
               para ver si el asiento se ha guardado correctamente.');
         $continuar = FALSE;
      }
      
      if($continuar)
      {
         $meses = array(
             '', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio',
             'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
         );
         
         $codcaja = '5700000000';
         if( isset($_POST['banco']) )
         {
            if($_POST['banco'] != '')
            {
               $codcaja = $_POST['banco'];
            }
         }
         
         /// asiento de cuota
         $asiento = new asiento();
         $asiento->codejercicio = $eje0->codejercicio;
         $asiento->concepto = 'Cuota de autónomo '.$meses[ intval(date('m', strtotime($_POST['fecha']))) ];
         $asiento->fecha = $_POST['fecha'];
         $asiento->importe = floatval($_POST['autonomo']);
         
         if( $asiento->save() )
         {
            $subc = $this->subcuenta->get_by_codigo('6420000000', $eje0->codejercicio);
            if($subc)
            {
               $partida = new partida();
               $partida->idasiento = $asiento->idasiento;
               $partida->coddivisa = $div0->coddivisa;
               $partida->tasaconv = $div0->tasaconv;
               $partida->concepto = $asiento->concepto;
               $partida->idsubcuenta = $subc->idsubcuenta;
               $partida->codsubcuenta = $subc->codsubcuenta;
               $partida->debe = $asiento->importe;
               $partida->save();
            }
            else
            {
               $this->new_error_msg('Subcuenta 6420000000 no encontrada.');
               $continuar = FALSE;
            }
            
            $subc = $this->subcuenta->get_by_codigo('4760000000', $eje0->codejercicio);
            if($subc)
            {
               $partida = new partida();
               $partida->idasiento = $asiento->idasiento;
               $partida->coddivisa = $div0->coddivisa;
               $partida->tasaconv = $div0->tasaconv;
               $partida->concepto = $asiento->concepto;
               $partida->idsubcuenta = $subc->idsubcuenta;
               $partida->codsubcuenta = $subc->codsubcuenta;
               $partida->haber = $asiento->importe;
               $partida->save();
            }
            else
            {
               $this->new_error_msg('Subcuenta 4760000000 no encontrada.');
               $continuar = FALSE;
            }
            
            if($continuar)
            {
               $this->new_message("<a href='".$asiento->url()."'>Asiento de autónomo</a> guardado correctamente!");
               
               /// asiento de pago
               $asiento = new asiento();
               $asiento->codejercicio = $eje0->codejercicio;
               $asiento->concepto = 'Pago de autónomo '.$meses[ intval(date('m', strtotime($_POST['fecha']))) ];
               $asiento->fecha = $_POST['fecha'];
               $asiento->importe = floatval($_POST['autonomo']);
               
               if( $asiento->save() )
               {
                  $subc = $this->subcuenta->get_by_codigo('4760000000', $eje0->codejercicio);
                  if($subc)
                  {
                     $partida = new partida();
                     $partida->idasiento = $asiento->idasiento;
                     $partida->coddivisa = $div0->coddivisa;
                     $partida->tasaconv = $div0->tasaconv;
                     $partida->concepto = $asiento->concepto;
                     $partida->idsubcuenta = $subc->idsubcuenta;
                     $partida->codsubcuenta = $subc->codsubcuenta;
                     $partida->debe = $asiento->importe;
                     $partida->save();
                  }
                  
                  $subc = $this->subcuenta->get_by_codigo($codcaja, $eje0->codejercicio);
                  if($subc)
                  {
                     $partida = new partida();
                     $partida->idasiento = $asiento->idasiento;
                     $partida->coddivisa = $div0->coddivisa;
                     $partida->tasaconv = $div0->tasaconv;
                     $partida->concepto = $asiento->concepto;
                     $partida->idsubcuenta = $subc->idsubcuenta;
                     $partida->codsubcuenta = $subc->codsubcuenta;
                     $partida->haber = $asiento->importe;
                     $partida->save();
                  }
                  
                  $this->new_message("<a href='".$asiento->url()."'>Asiento de pago</a> guardado correctamente!");
               }
            }
            else
            {
               if( $asiento->delete() )
               {
                  $this->new_error_msg("¡Error en alguna de las partidas! Se ha borrado el asiento.");
               }
               else
                  $this->new_error_msg("¡Error en alguna de las partidas! Además ha sido imposible borrar el asiento.");
            }
         }
         else
         {
            $this->new_error_msg("¡Imposible guardar el asiento!");
         }
      }
   }
   
   private function nuevo_asiento_modelo130()
   {
      $continuar = TRUE;
      
      $eje0 = $this->get_ejercicio($_POST['fecha']);
      if(!$eje0)
      {
         $continuar = FALSE;
      }
      
      $div0 = $this->divisa->get($_POST['divisa']);
      if(!$div0)
      {
         $this->new_error_msg('Divisa no encontrada.');
         $continuar = FALSE;
      }
      
      if( $this->duplicated_petition($_POST['petition_id']) )
      {
         $this->new_error_msg('Petición duplicada. Has hecho doble clic sobre el botón Guardar
               y se han enviado dos peticiones. Mira en <a href="'.$this->ppage->url().'">asientos</a>
               para ver si el asiento se ha guardado correctamente.');
         $continuar = FALSE;
      }
      
      if($continuar)
      {
         $meses = array(
             '', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio',
             'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
         );
         
         $codcaja = '5700000000';
         if( isset($_POST['banco130']) )
         {
            if($_POST['banco130'] != '')
            {
               $codcaja = $_POST['banco130'];
            }
         }
         
         /// asiento de cuota
         $asiento = new asiento();
         $asiento->codejercicio = $eje0->codejercicio;
         $asiento->concepto = 'Pago modelo 130 '.$meses[ intval(date('m', strtotime($_POST['fecha']))) ];
         $asiento->fecha = $_POST['fecha'];
         $asiento->importe = floatval($_POST['modelo130']);
         
         if( $asiento->save() )
         {
            $subc = $this->subcuenta->get_by_codigo('4730000000', $eje0->codejercicio);
            if($subc)
            {
               $partida = new partida();
               $partida->idasiento = $asiento->idasiento;
               $partida->coddivisa = $div0->coddivisa;
               $partida->tasaconv = $div0->tasaconv;
               $partida->concepto = $asiento->concepto;
               $partida->idsubcuenta = $subc->idsubcuenta;
               $partida->codsubcuenta = $subc->codsubcuenta;
               $partida->debe = $asiento->importe;
               $partida->save();
            }
            else
            {
               $this->new_error_msg('Subcuenta 4730000000 no encontrada.');
               $continuar = FALSE;
            }
            
            $subc = $this->subcuenta->get_by_codigo($codcaja, $eje0->codejercicio);
            if($subc)
            {
               $partida = new partida();
               $partida->idasiento = $asiento->idasiento;
               $partida->coddivisa = $div0->coddivisa;
               $partida->tasaconv = $div0->tasaconv;
               $partida->concepto = $asiento->concepto;
               $partida->idsubcuenta = $subc->idsubcuenta;
               $partida->codsubcuenta = $subc->codsubcuenta;
               $partida->haber = $asiento->importe;
               $partida->save();
            }
            else
            {
               $this->new_error_msg('Subcuenta '.$codcaja.' no encontrada.');
               $continuar = FALSE;
            }
            
            if($continuar)
            {
               $this->new_message("<a href='".$asiento->url()."'>Asiento de pago</a> guardado correctamente!");
            }
            else
            {
               if( $asiento->delete() )
               {
                  $this->new_error_msg("¡Error en alguna de las partidas! Se ha borrado el asiento.");
               }
               else
                  $this->new_error_msg("¡Error en alguna de las partidas! Además ha sido imposible borrar el asiento.");
            }
         }
         else
         {
            $this->new_error_msg("¡Imposible guardar el asiento!");
         }
      }
   }
   
   private function nuevo_asiento_traspasos()
   {
      $continuar = TRUE;
      
      $eje0 = $this->get_ejercicio($_POST['fecha']);
      if(!$eje0)
      {
         $continuar = FALSE;
      }
      
      $div0 = $this->divisa->get($_POST['divisa']);
      if(!$div0)
      {
         $this->new_error_msg('Divisa no encontrada.');
         $continuar = FALSE;
      }
      
      if( $this->duplicated_petition($_POST['petition_id']) )
      {
         $this->new_error_msg('Petición duplicada. Has hecho doble clic sobre el botón Guardar
               y se han enviado dos peticiones. Mira en <a href="'.$this->ppage->url().'">asientos</a>
               para ver si el asiento se ha guardado correctamente.');
         $continuar = FALSE;
      }
      
      if($continuar)
      {
         $meses = array(
             '', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio',
             'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
         );
         
         // Comprueba la existencia de las cuentas origen y destino, en caso contrario las crea.
         
         $coddestino = '5700000000';
         if( isset($_POST['destino']) )
         {
            if($_POST['destino'] != '')
            {
               $coddestino = $_POST['destino'];
            }
         }
         
         $subc = $this->cuenta_banco->get($coddestino);
         if($subc)
         {

						if (isset($subc->codsubcuenta))
						{
								$coddestino = $subc->codsubcuenta;
																		        
						}else 
								$coddestino = '5720000000';
						
						$this->crea_subcuentas($eje0->codejercicio, '572', $coddestino, 'Bancos e instituciones de crédito c/c vista, euros - ' .$subc->descripcion);
						$destino_desc = $subc->descripcion;
						    
        }
         
         $codcaja = '5700000000';
         if( isset($_POST['bancotras']) )
         {
            if($_POST['bancotras'] != '')
            {
               $codcaja = $_POST['bancotras'];
            }
         }
         
         $subc = $this->cuenta_banco->get($codcaja);
         if($subc)
         {

						if (isset($subc->codsubcuenta))
						{
								$codcaja = $subc->codsubcuenta;
																		        
						}else 
								$codcaja = '5720000000';
						
						$this->crea_subcuentas($eje0->codejercicio, '572', $codcaja, 'Bancos e instituciones de crédito c/c vista, euros - ' .$subc->descripcion);
						$desde_desc = $subc->descripcion;
						    
        }         
               
         /// asiento de cuota
         $asiento = new asiento();
         $asiento->codejercicio = $eje0->codejercicio;
         $asiento->concepto = 'Traspaso '.$meses[ intval(date('m', strtotime($_POST['fecha']))) ] . ' (' . $desde_desc . ' a ' . $destino_desc . ')';
         $asiento->fecha = $_POST['fecha'];
         $asiento->importe = floatval($_POST['traspasos']);
         
         if( $asiento->save() )
         {
            
            $subc = $this->subcuenta->get_by_codigo($coddestino, $eje0->codejercicio);
            if($subc)
            {
               $partida = new partida();
               $partida->idasiento = $asiento->idasiento;
               $partida->coddivisa = $div0->coddivisa;
               $partida->tasaconv = $div0->tasaconv;
               $partida->concepto = $asiento->concepto;
               $partida->idsubcuenta = $subc->idsubcuenta;
               $partida->codsubcuenta = $subc->codsubcuenta;
               $partida->debe = $asiento->importe;
               $partida->save();
            }
            else
            {
               $this->new_error_msg('Subcuenta '.$coddestino.' no encontrada.');
               $continuar = FALSE;
            }
            
            $subc = $this->subcuenta->get_by_codigo($codcaja, $eje0->codejercicio);
            if($subc)
            {
               $partida = new partida();
               $partida->idasiento = $asiento->idasiento;
               $partida->coddivisa = $div0->coddivisa;
               $partida->tasaconv = $div0->tasaconv;
               $partida->concepto = $asiento->concepto;
               $partida->idsubcuenta = $subc->idsubcuenta;
               $partida->codsubcuenta = $subc->codsubcuenta;
               $partida->haber = $asiento->importe;
               $partida->save();
            }
            else
            {
               $this->new_error_msg('Subcuenta '.$codcaja.' no encontrada.');
               $continuar = FALSE;
            }
            
            if($continuar)
            {
               $this->new_message("<a href='".$asiento->url()."'>Asiento de traspaso</a> guardado correctamente!");
            }
            else
            {
               if( $asiento->delete() )
               {
                  $this->new_error_msg("¡Error en alguna de las partidas! Se ha borrado el asiento.");
               }
               else
                  $this->new_error_msg("¡Error en alguna de las partidas! Además ha sido imposible borrar el asiento.");
            }
         }
         else
         {
            $this->new_error_msg("¡Imposible guardar el asiento!");
         }
      }
   }
   
   private function nuevo_asiento_anticipos()
   {
      $continuar = TRUE;
      
      $eje0 = $this->get_ejercicio($_POST['fecha']);
      if(!$eje0)
      {
         $continuar = FALSE;
      }
      
      $div0 = $this->divisa->get($_POST['divisa']);
      if(!$div0)
      {
         $this->new_error_msg('Divisa no encontrada.');
         $continuar = FALSE;
      }
      
      if( $this->duplicated_petition($_POST['petition_id']) )
      {
         $this->new_error_msg('Petición duplicada. Has hecho doble clic sobre el botón Guardar
               y se han enviado dos peticiones. Mira en <a href="'.$this->ppage->url().'">asientos</a>
               para ver si el asiento se ha guardado correctamente.');
         $continuar = FALSE;
      }
      
      if($continuar)
      {
         $meses = array(
             '', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio',
             'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
         );
         
         if( isset($_POST['proveedor']) )
         {
            if($_POST['proveedor'] != '')
            {
               $coddestino = 4070000000 + intval($_POST['proveedor']);
            }
         }
         
         $subc = $this->proveedor->get($_POST['proveedor']);
         if(!$subc)
         {
               $this->new_error_msg('Proveedor '.$_POST['proveedor'].' no encontrado.');
               $continuar = FALSE;
         }
         		
         $this->crea_subcuentas($eje0->codejercicio, '407', $coddestino, 'Anticipos a proveedores - ' .$subc->razonsocial);
         $Razon_social = $subc->razonsocial;
         
         /////////////////////////////////////////
         
         $codcaja = '5700000000';
         if( isset($_POST['bancoanticipo']) )
         {
            if($_POST['bancoanticipo'] != '')
            {
               $codcaja = $_POST['bancoanticipo'];
            }
         }
         
         $subc = $this->cuenta_banco->get($codcaja);
         if($subc)
         {

						if (isset($subc->codsubcuenta))
						{
								$codcaja = $subc->codsubcuenta;
																		        
						}else 
								$codcaja = '5720000000';
						
						$this->crea_subcuentas($eje0->codejercicio, '572', $codcaja, 'Bancos e instituciones de crédito c/c vista, euros - ' .$subc->descripcion);
						    
        }
         
         $codtipoiva = 'IVA21';
         if( isset($_POST['tipoiva']) )
         {
            if($_POST['tipoiva'] != '')
            {
               $codtipoiva = $_POST['tipoiva'];
            }
         }
         
         $subc = $this->impuesto->get($codtipoiva);
         if($subc)
         {

						if (isset($subc->codsubcuentasop))
						{
								$codivasoportado = $subc->codsubcuentasop;
																		        
						}else 
								$codivasoportado = '4720000000';
						
						$this->crea_subcuentas($eje0->codejercicio, '472', $codivasoportado, $subc->descripcion);	
						$tipoiva = $subc->iva;
                
        }else
	     		 $this->new_error_msg('Tipo de IVA  ' .$codtipoiva. ' no encontrado');
                	     		
         /// asiento de cuota
         $asiento = new asiento();
         $asiento->codejercicio = $eje0->codejercicio;                    
         $asiento->concepto = 'Anticipo a ' . $Razon_social;
         $asiento->fecha = $_POST['fecha'];
         $asiento->importe = floatval($_POST['anticipos']);
         $parte_iva = round(($asiento->importe * ($tipoiva/100)),FS_NF0);
         $parte_base = round(($asiento->importe - $parte_iva),FS_NF0);
         
         if( $asiento->save() )
         {    
            $subc = $this->subcuenta->get_by_codigo($coddestino, $eje0->codejercicio);
            if($subc)
            {
               $partida = new partida();
               $partida->idasiento = $asiento->idasiento;
               $partida->coddivisa = $div0->coddivisa;
               $partida->tasaconv = $div0->tasaconv;
               $partida->concepto = $asiento->concepto;
               $partida->idsubcuenta = $subc->idsubcuenta;
               $partida->codsubcuenta = $subc->codsubcuenta;
               $partida->debe = $parte_base;
               $partida->save();
            }
            else
            {
               $this->new_error_msg('Subcuenta '.$coddestino.' no encontrada.');
               $continuar = FALSE;
            }
            
            $subc = $this->subcuenta->get_by_codigo($codivasoportado, $eje0->codejercicio);
            if($subc)
            {
               $partida = new partida();
               $partida->idasiento = $asiento->idasiento;
               $partida->coddivisa = $div0->coddivisa;
               $partida->tasaconv = $div0->tasaconv;
               $partida->concepto = $asiento->concepto;
               $partida->idsubcuenta = $subc->idsubcuenta;
               $partida->codsubcuenta = $subc->codsubcuenta;
               $partida->debe = $parte_iva;
               $partida->save();
            }
            else
            {
               $this->new_error_msg('Subcuenta '.$codivasoportado.' no encontrada.');
               $continuar = FALSE;
            }
            
            $subc = $this->subcuenta->get_by_codigo($codcaja, $eje0->codejercicio);
            if($subc)
            {
               $partida = new partida();
               $partida->idasiento = $asiento->idasiento;
               $partida->coddivisa = $div0->coddivisa;
               $partida->tasaconv = $div0->tasaconv;
               $partida->concepto = $asiento->concepto;
               $partida->idsubcuenta = $subc->idsubcuenta;
               $partida->codsubcuenta = $subc->codsubcuenta;
               $partida->haber = $asiento->importe;
               $partida->save();
            }
            else
            {
               $this->new_error_msg('Subcuenta '.$codcaja.' no encontrada.');
               $continuar = FALSE;
            }
            
            if($continuar)
            {
               $this->new_message("<a href='".$asiento->url()."'>Asiento de anticipo</a> guardado correctamente!");
            }
            else
            {
               if( $asiento->delete() )
               {
                  $this->new_error_msg("¡Error en alguna de las partidas! Se ha borrado el asiento.");
               }
               else
                  $this->new_error_msg("¡Error en alguna de las partidas! Además ha sido imposible borrar el asiento.");
            }
         }
         else
         {
            $this->new_error_msg("¡Imposible guardar el asiento!");
         }
      }
   }
   
   private function nuevo_asiento_nomina_ajena()
   {
      $continuar = TRUE;
      
      $eje0 = $this->get_ejercicio($_POST['fecha']);
      if(!$eje0)
      {
         $continuar = FALSE;
      }
      
      $div0 = $this->divisa->get($_POST['divisa']);
      if(!$div0)
      {
         $this->new_error_msg('Divisa no encontrada.');
         $continuar = FALSE;
      }
      
      if( $this->duplicated_petition($_POST['petition_id']) )
      {
         $this->new_error_msg('Petición duplicada. Has hecho doble clic sobre el botón Guardar
               y se han enviado dos peticiones. Mira en <a href="'.$this->ppage->url().'">asientos</a>
               para ver si el asiento se ha guardado correctamente.');
         $continuar = FALSE;
      }
      
      if($continuar)
      {
         $meses = array(
             '', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio',
             'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
         );
         
                 
         $codcaja = '5700000000';
         if( isset($_POST['banco_nomina_ajena']) )
         {
            if($_POST['banco_nomina_ajena'] != '')
            {
               $codcaja = $_POST['banco_nomina_ajena'];
            }
         }
         
         $subc = $this->cuenta_banco->get($codcaja);
         if($subc)
         {

						if (isset($subc->codsubcuenta))
						{
								$codcaja = $subc->codsubcuenta;
																		        
						}else 
								$codcaja = '5720000000';
						
						$this->crea_subcuentas($eje0->codejercicio, '572', $codcaja, 'Bancos e instituciones de crédito c/c vista, euros - ' .$subc->descripcion);
						    
        }         
         
         $subc = $this->agente->get($_POST['cod_ajena']);
         if($subc)
         {
               
               $Nombre_empleado = $subc->nombre . ' ' . $subc->apellidos;
         }
                       
         /// asiento de la nómina
        
         $asiento = new asiento();
         $asiento->codejercicio = $eje0->codejercicio;

         $asiento->concepto = 'Nómina mes de '.$meses[ intval(date('m', strtotime($_POST['fecha']))) ] . ' (' . $Nombre_empleado . ')';
         $asiento->fecha = $_POST['fecha'];
         
        
         /// Crea las subcuentas del empleado si no existen
              
         ///  Cuenta 640       
         
         $codigo_cuenta_640 = 6400000000 + intval($_POST['cod_ajena']);
 
			   $subc = $this->subcuenta->get_by_codigo((string) $codigo_cuenta_640, $eje0->codejercicio);
			   if(!$subc)
         {
               $this->crea_subcuentas($eje0->codejercicio, '640', $codigo_cuenta_640, 'Sueldos y salarios - ' .$Nombre_empleado);
         }
         
         ///  Cuenta 642       
         
         $codigo_cuenta_642 = 6420000000 + intval($_POST['cod_ajena']);
 
			   $subc = $this->subcuenta->get_by_codigo((string) $codigo_cuenta_642, $eje0->codejercicio);
         if(!$subc)
         {
         			$this->crea_subcuentas($eje0->codejercicio, '642', $codigo_cuenta_642, 'Seguridad social a cargo de la empresa - ' .$Nombre_empleado);
         }
         		
         ///  Cuenta 476       
         
         $codigo_cuenta_476 = 4760000000 + intval($_POST['cod_ajena']);
         
         $subc = $this->subcuenta->get_by_codigo((string) $codigo_cuenta_476, $eje0->codejercicio);
         if(!$subc)
         {
               $this->crea_subcuentas($eje0->codejercicio, '476', $codigo_cuenta_476, 'Organismos de la seguridad social, acreedores - ' .$Nombre_empleado);
         }
                  
         ///  Cuenta 4751       
         
         $codigo_cuenta_4751 = 4751000000 + intval($_POST['cod_ajena']);
         
         $subc = $this->subcuenta->get_by_codigo((string) $codigo_cuenta_4751, $eje0->codejercicio);
         if(!$subc)
         {
               $this->crea_subcuentas($eje0->codejercicio, '4751', $codigo_cuenta_4751, 'Hacienda pública, acreedora por retenciones practicadas - ' .$Nombre_empleado);
         }
         		
         ///  Cuenta 465       
         
         $codigo_cuenta_465 = 4650000000 + intval($_POST['cod_ajena']);
         
         $subc = $this->subcuenta->get_by_codigo((string) $codigo_cuenta_465, $eje0->codejercicio);
         if(!$subc)
         {
               $this->crea_subcuentas($eje0->codejercicio, '465', $codigo_cuenta_465, 'Remuneraciones pendientes de pago - ' .$Nombre_empleado);
         }
         		
         // Inicio del asiento       
                 
         $Importe_salario_bruto = floatval($_POST['sal_bruto_ajena']);
         $Importe_cuota_patronal = floatval($_POST['cuota_patronal']);
         $Importe_cuota_obrera = floatval($_POST['cuota_obrera']);
         $Importe_cuota_ajena = $Importe_cuota_patronal + $Importe_cuota_obrera;
         $Importe_retenciones = round(($Importe_salario_bruto * floatval($_POST['retenciones_ajena']))/100,FS_NF0);
         $Importe_pendiente_pago = $Importe_salario_bruto - $Importe_cuota_obrera - $Importe_retenciones;
         
         $asiento->importe = $Importe_salario_bruto + $Importe_cuota_patronal;
         
         if( $asiento->save() )
         {
                        
            $subc = $this->subcuenta->get_by_codigo($codigo_cuenta_476, $eje0->codejercicio);
            if($subc)
            {
               $partida = new partida();
               $partida->idasiento = $asiento->idasiento;
               $partida->coddivisa = $div0->coddivisa;
               $partida->tasaconv = $div0->tasaconv;
               $partida->concepto = $asiento->concepto;
               $partida->idsubcuenta = $subc->idsubcuenta;
               $partida->codsubcuenta = $codigo_cuenta_476;
               $partida->haber = $Importe_cuota_ajena;
               $partida->save();
            }
            else
            {
               $this->new_error_msg('Subcuenta '.$codigo_cuenta_476.' no encontrada.');
               $continuar = FALSE;
            }
            
            $subc = $this->subcuenta->get_by_codigo($codigo_cuenta_4751, $eje0->codejercicio);
            if($subc)
            {   
               $partida = new partida();
               $partida->idasiento = $asiento->idasiento;
               $partida->coddivisa = $div0->coddivisa;
               $partida->tasaconv = $div0->tasaconv;
               $partida->concepto = $asiento->concepto;
               $partida->idsubcuenta = $subc->idsubcuenta;
               $partida->codsubcuenta = $codigo_cuenta_4751;
               $partida->haber = $Importe_retenciones;
               $partida->save();
            }
            else
            {
               $this->new_error_msg('Subcuenta '.$codigo_cuenta_4751.' no encontrada.');
               $continuar = FALSE;
            }
            
            $subc = $this->subcuenta->get_by_codigo($codigo_cuenta_465, $eje0->codejercicio);
            if($subc)
            {
               $partida = new partida();
               $partida->idasiento = $asiento->idasiento;
               $partida->coddivisa = $div0->coddivisa;
               $partida->tasaconv = $div0->tasaconv;
               $partida->concepto = $asiento->concepto;
               $partida->idsubcuenta = $subc->idsubcuenta;
               $partida->codsubcuenta = $codigo_cuenta_465;
               $partida->haber = $Importe_pendiente_pago;
               $partida->save();
            }
            else
            {
               $this->new_error_msg('Subcuenta '.$codigo_cuenta_465.' no encontrada.');
               $continuar = FALSE;
            }
            
            $subc = $this->subcuenta->get_by_codigo($codigo_cuenta_640, $eje0->codejercicio);
            if($subc)
            {
               $partida = new partida();
               $partida->idasiento = $asiento->idasiento;
               $partida->coddivisa = $div0->coddivisa;
               $partida->tasaconv = $div0->tasaconv;
               $partida->concepto = $asiento->concepto;
               $partida->idsubcuenta = $subc->idsubcuenta;
               $partida->codsubcuenta = $codigo_cuenta_640;
               $partida->debe = $Importe_salario_bruto;
               $partida->save();
            }
            else
            {
               $this->new_error_msg('Subcuenta '.$codigo_cuenta_640.' no encontrada.');
               $continuar = FALSE;
            }
            
            $subc = $this->subcuenta->get_by_codigo($codigo_cuenta_642, $eje0->codejercicio);
            if($subc)
            {
               $partida = new partida();
               $partida->idasiento = $asiento->idasiento;
               $partida->coddivisa = $div0->coddivisa;
               $partida->tasaconv = $div0->tasaconv;
               $partida->concepto = $asiento->concepto;
               $partida->idsubcuenta = $subc->idsubcuenta;
               $partida->codsubcuenta = $codigo_cuenta_642;
               $partida->debe = $Importe_cuota_patronal;
               $partida->save();
            }
            else
            {
               $this->new_error_msg('Subcuenta '.$codigo_cuenta_642.' no encontrada.');
               $continuar = FALSE;
            }
            
            if($continuar)
            {
               $this->new_message("<a href='".$asiento->url()."'>Asiento de nómina por cuenta ajena</a> guardado correctamente!");
            }
            else
            {
               if( $asiento->delete() )
               {
                  $this->new_error_msg("¡Error en alguna de las partidas! Se ha borrado el asiento.");
               }
               else
                  $this->new_error_msg("¡Error en alguna de las partidas! Además ha sido imposible borrar el asiento.");
            }
         }
         else
         {
            $this->new_error_msg("¡Imposible guardar el asiento!");
         }
         
          /// asiento pago de la nómina
        
         $asiento = new asiento();
         $asiento->codejercicio = $eje0->codejercicio;

         $asiento->concepto = 'Pago nómina mes de '.$meses[ intval(date('m', strtotime($_POST['fecha']))) ] . ' (' . $Nombre_empleado . ')';
         $asiento->fecha = $_POST['fecha'];
         
         $asiento->importe = $Importe_pendiente_pago;
         
         if( $asiento->save() )
         {
            $subc = $this->subcuenta->get_by_codigo($codigo_cuenta_465, $eje0->codejercicio);
            if($subc)
            {
               $partida = new partida();
               $partida->idasiento = $asiento->idasiento;
               $partida->coddivisa = $div0->coddivisa;
               $partida->tasaconv = $div0->tasaconv;
               $partida->concepto = $asiento->concepto;
               $partida->idsubcuenta = $subc->idsubcuenta;
               $partida->codsubcuenta = $codigo_cuenta_465;
               $partida->debe = $Importe_pendiente_pago;
               $partida->save();
            }
            else
            {
               $this->new_error_msg('Subcuenta '.$codigo_cuenta_465.' no encontrada.');
               $continuar = FALSE;
            }       
            
            $subc = $this->subcuenta->get_by_codigo($codcaja, $eje0->codejercicio);
            if($subc)
            {
               $partida = new partida();
               $partida->idasiento = $asiento->idasiento;
               $partida->coddivisa = $div0->coddivisa;
               $partida->tasaconv = $div0->tasaconv;
               $partida->concepto = $asiento->concepto;
               $partida->idsubcuenta = $subc->idsubcuenta;
               $partida->codsubcuenta = $subc->codsubcuenta;
               $partida->haber = $Importe_pendiente_pago;
               $partida->save();
            }
            else
            {
               $this->new_error_msg('Subcuenta '.$codcaja.' no encontrada.');
               $continuar = FALSE;
            }
            
            if($continuar)
            {
               $this->new_message("<a href='".$asiento->url()."'>Asiento de pago de nómina por cuenta ajena</a> guardado correctamente!");
            }
            else
            {
               if( $asiento->delete() )
               {
                  $this->new_error_msg("¡Error en alguna de las partidas! Se ha borrado el asiento.");
               }
               else
                  $this->new_error_msg("¡Error en alguna de las partidas! Además ha sido imposible borrar el asiento.");
            }
         }
         else
         {
            $this->new_error_msg("¡Imposible guardar el asiento!");
         }
      } 
   }
   
   private function nuevo_asiento_alquiler()
   {
      $continuar = TRUE;
      
      $eje0 = $this->get_ejercicio($_POST['fecha']);
      if(!$eje0)
      {
         $continuar = FALSE;
      }
      
      $div0 = $this->divisa->get($_POST['divisa']);
      if(!$div0)
      {
         $this->new_error_msg('Divisa no encontrada.');
         $continuar = FALSE;
      }
      
      if( $this->duplicated_petition($_POST['petition_id']) )
      {
         $this->new_error_msg('Petición duplicada. Has hecho doble clic sobre el botón Guardar
               y se han enviado dos peticiones. Mira en <a href="'.$this->ppage->url().'">asientos</a>
               para ver si el asiento se ha guardado correctamente.');
         $continuar = FALSE;
      }
      
      if($continuar)
      {
         $meses = array(
             '', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio',
             'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
         );
         
         // Establece el codigo de cuenta de pago y si no existe lo crea       
                
         $codcaja = '5700000000';
         if( isset($_POST['banco_alquiler']) )
         {
            if($_POST['banco_alquiler'] != '')
            {
               $codcaja = $_POST['banco_alquiler'];
            }
         }
         
         $subc = $this->cuenta_banco->get($codcaja);
         if($subc)
         {

						if (isset($subc->codsubcuenta))
						{
								$codcaja = $subc->codsubcuenta;
																		        
						}else 
								$codcaja = '5720000000';
						
						$this->crea_subcuentas($eje0->codejercicio, '572', $codcaja, 'Bancos e instituciones de crédito c/c vista, euros - ' .$subc->descripcion);
						    
        }
	     	     	     		 
	     	// Crea la subcuenta del tipo de IVA en la cuenta 472 si no existe	 
         
         $codtipoiva = 'IVA21';
         if( isset($_POST['tipoiva_alquiler']) )
         {
            if($_POST['tipoiva_alquiler'] != '')
            {
               $codtipoiva = $_POST['tipoiva_alquiler'];
            }
         }
         
         $subc = $this->impuesto->get($codtipoiva);
         if($subc)
         {

						if (isset($subc->codsubcuentasop))
						{
								$codivasoportado = $subc->codsubcuentasop;
																		        
						}else 
								$codivasoportado = '4720000000';
						
						$this->crea_subcuentas($eje0->codejercicio, '472', $codivasoportado, $subc->descripcion);	
						$tipoiva = $subc->iva;
                
        }else
	     		 $this->new_error_msg('Tipo de IVA  ' .$codtipoiva. ' no encontrado');
         
         
         /// Crea la subcuenta del acreedor en la cuenta 410 si no existe
         
         $cod_acreedor = '4100000000';
         if( isset($_POST['acreedor_alquiler']) )
         {
            if($_POST['acreedor_alquiler'] != '')
            {
               // Los acreedores se crean a partir de la 41002 para evitor asignaciones erroneas sobre empleados con la mismo código
               $cod_acreedor = 4100200000 + intval($_POST['acreedor_alquiler']);  
            }
         }
         
         $subc = $this->proveedor->get($_POST['acreedor_alquiler']);
         if(!$subc)
         {
               $this->crea_subcuentas($eje0->codejercicio, '4100', $cod_acreedor, 'Acreedores por prestaciones de servicios - ' .$subc->razonsocial);
         }                
         
         $descripcion_alquiler = $_POST['concepto_alquiler'];
        
         ///  Crea la subcuenta del acreedor en la cuenta 4751 si no existe      
         // Los acreedores se crean a partir de la 47512 para evitor asignaciones erroneas sobre empleados con la mismo código
         
         $codigo_cuenta_4751 = 4751200000 + intval($_POST['acreedor_alquiler']);
         
         $subc = $this->proveedor->get($_POST['acreedor_alquiler']);
         if(!$subc)
         {
               $this->crea_subcuentas($eje0->codejercicio, '4751', $codigo_cuenta_4751, 'Hacienda pública, acreedora por retenciones practicadas - ' .$subc->razonsocial);
         }
             
         /// Asiento del alquiler
         
         $Importe_alquiler = floatval($_POST['importe_alquiler']);
         $Retenciones_alquiler = floatval($_POST['retenciones_alquiler']);
                 
         $Importe_iva_alquiler = round(($Importe_alquiler * $tipoiva)/100,FS_NF0);
         $Importe_retenciones_alquiler = round(($Importe_alquiler * $Retenciones_alquiler)/100,FS_NF0);
         $Total_importe = $Importe_alquiler + ($Importe_iva_alquiler - $Importe_retenciones_alquiler);
        
         $asiento = new asiento();
         $asiento->codejercicio = $eje0->codejercicio;

         $asiento->concepto = 'Alquiler mes de '.$meses[ intval(date('m', strtotime($_POST['fecha']))) ] . ' ' . $descripcion_alquiler;
         $asiento->fecha = $_POST['fecha'];
         $asiento->importe = $Importe_alquiler + $Importe_iva_alquiler;
                        
         if( $asiento->save() )
         {
                        
            $subc = $this->subcuenta->get_by_codigo('6210000000', $eje0->codejercicio);
            if($subc)
            {
               $partida = new partida();
               $partida->idasiento = $asiento->idasiento;
               $partida->coddivisa = $div0->coddivisa;
               $partida->tasaconv = $div0->tasaconv;
               $partida->concepto = $asiento->concepto;
               $partida->idsubcuenta = $subc->idsubcuenta;
               $partida->codsubcuenta = '6210000000';
               $partida->debe = $Importe_alquiler;
               $partida->save();
            }
            else
            {
               //$this->new_error_msg('Subcuenta '.$codigo_cuenta.' no encontrada.');
               $this->new_error_msg('Subcuenta 6210000000 no encontrada.');
               $continuar = FALSE;
            }
            
            $subc = $this->subcuenta->get_by_codigo($codigo_cuenta_4751, $eje0->codejercicio);
            if($subc)
            {   
               $partida = new partida();
               $partida->idasiento = $asiento->idasiento;
               $partida->coddivisa = $div0->coddivisa;
               $partida->tasaconv = $div0->tasaconv;
               $partida->concepto = $asiento->concepto;
               $partida->idsubcuenta = $subc->idsubcuenta;
               $partida->codsubcuenta = $codigo_cuenta_4751;
               $partida->haber = $Importe_retenciones_alquiler;
               $partida->save();
            }
            else
            {
               $this->new_error_msg('Subcuenta '.$codigo_cuenta_4751.' no encontrada.');
               $continuar = FALSE;
            }
            
            $subc = $this->subcuenta->get_by_codigo($codivasoportado, $eje0->codejercicio);
            if($subc)
            {
               $partida = new partida();
               $partida->idasiento = $asiento->idasiento;
               $partida->coddivisa = $div0->coddivisa;
               $partida->tasaconv = $div0->tasaconv;
               $partida->concepto = $asiento->concepto;
               $partida->idsubcuenta = $subc->idsubcuenta;
               $partida->codsubcuenta = $codivasoportado;
               $partida->debe = $Importe_iva_alquiler;
               $partida->save();
            }
            else
            {
               $this->new_error_msg('Subcuenta '.$codivasoportado.' no encontrada.');
               $continuar = FALSE;
            }
            
            $subc = $this->subcuenta->get_by_codigo($cod_acreedor, $eje0->codejercicio);
            if($subc)
            {
               $partida = new partida();
               $partida->idasiento = $asiento->idasiento;
               $partida->coddivisa = $div0->coddivisa;
               $partida->tasaconv = $div0->tasaconv;
               $partida->concepto = $asiento->concepto;
               $partida->idsubcuenta = $subc->idsubcuenta;
               $partida->codsubcuenta = $cod_acreedor;
               $partida->haber = $Total_importe;
               $partida->save();
            }
            else
            {
               $this->new_error_msg('Subcuenta '.$codigo_cuenta_640.' no encontrada.');
               $continuar = FALSE;
            }

            if($continuar)
            {
               $this->new_message("<a href='".$asiento->url()."'>Asiento de alquiler</a> guardado correctamente!");
            }
            else
            {
               if( $asiento->delete() )
               {
                  $this->new_error_msg("¡Error en alguna de las partidas! Se ha borrado el asiento.");
               }
               else
                  $this->new_error_msg("¡Error en alguna de las partidas! Además ha sido imposible borrar el asiento.");
            }
         }
         else
         {
            $this->new_error_msg("¡Imposible guardar el asiento!");
         }
         
          /// asiento pago del alquiler
        
         $asiento = new asiento();
         $asiento->codejercicio = $eje0->codejercicio;

         $asiento->concepto = 'Pago alquiler mes de '.$meses[ intval(date('m', strtotime($_POST['fecha']))) ] . ' ' . $descripcion_alquiler;
         $asiento->fecha = $_POST['fecha'];
         $asiento->importe = $Total_importe;
       
         if( $asiento->save() )
         {
            $subc = $this->subcuenta->get_by_codigo($cod_acreedor, $eje0->codejercicio);
            if($subc)
            {
               $partida = new partida();
               $partida->idasiento = $asiento->idasiento;
               $partida->coddivisa = $div0->coddivisa;
               $partida->tasaconv = $div0->tasaconv;
               $partida->concepto = $asiento->concepto;
               $partida->idsubcuenta = $subc->idsubcuenta;
               $partida->codsubcuenta = $cod_acreedor;
               $partida->debe = $asiento->importe;
               $partida->save();
            }
            else
            {
               $this->new_error_msg('Subcuenta '.$codigo_cuenta_465.' no encontrada.');
               $continuar = FALSE;
            }       
            
            $subc = $this->subcuenta->get_by_codigo($codcaja, $eje0->codejercicio);
            if($subc)
            {
               $partida = new partida();
               $partida->idasiento = $asiento->idasiento;
               $partida->coddivisa = $div0->coddivisa;
               $partida->tasaconv = $div0->tasaconv;
               $partida->concepto = $asiento->concepto;
               $partida->idsubcuenta = $subc->idsubcuenta;
               $partida->codsubcuenta = $subc->codsubcuenta;
               $partida->haber = $asiento->importe;
               $partida->save();
            }
            else
            {
               $this->new_error_msg('Subcuenta '.$codcaja.' no encontrada.');
               $continuar = FALSE;
            }
            
            if($continuar)
            {
               $this->new_message("<a href='".$asiento->url()."'>Asiento de pago de alquiler</a> guardado correctamente!");
            }
            else
            {
               if( $asiento->delete() )
               {
                  $this->new_error_msg("¡Error en alguna de las partidas! Se ha borrado el asiento.");
               }
               else
                  $this->new_error_msg("¡Error en alguna de las partidas! Además ha sido imposible borrar el asiento.");
            }
         }
         else
         {
            $this->new_error_msg("¡Imposible guardar el asiento!");
         }
      } 
   }
   
   private function copiar_asiento()
   {
      $copia = $this->asiento->get($_GET['copy']);
      if($copia)
      {
         $this->asiento->concepto = $copia->concepto;
         
         foreach($copia->get_partidas() as $part)
         {
            $subc = $this->subcuenta->get($part->idsubcuenta);
            if($subc)
            {
               $part->desc_subcuenta = $subc->descripcion;
               $part->saldo = $subc->saldo;
            }
            else
            {
               $part->desc_subcuenta = '';
               $part->saldo = 0;
            }
            
            $this->lineas[] = $part;
         }
         
         $this->new_advice('Copiando asiento '.$copia->numero.'. Pulsa <b>guardar</b> para terminar.');
      }
      else
      {
         $this->new_error_msg('Asiento no encontrado.');
      }
   }
   
   private function new_search()
   {
      /// cambiamos la plantilla HTML
      $this->template = 'ajax/contabilidad_nuevo_asiento';
      
      $eje0 = $this->ejercicio->get_by_fecha($_POST['fecha']);
      if($eje0)
      {
         $this->resultados = $this->subcuenta->search_by_ejercicio($eje0->codejercicio, $this->query);
      }
      else
      {
         $this->resultados = array();
         $this->new_error_msg('Ningún ejercicio encontrado para la fecha '.$_POST['fecha']);
      }
   }
   
   private function check_datos_contables()
   {
      $eje = $this->ejercicio->get_by_fecha( $this->today() );
      if($eje)
      {
         $ok = FALSE;
         foreach($this->subcuenta->all_from_ejercicio($eje->codejercicio, TRUE, 5) as $subc)
         {
            $ok = TRUE;
            break;
         }
         
         if(!$ok)
         {
            $this->new_error_msg('No se encuentran subcuentas para el ejercicio '.$eje->nombre
                    .' ¿<a href="'.$eje->url().'">Has importado los datos de contabilidad</a>?');
         }
      }
      else
      {
         $this->new_error_msg('No se encuentra ningún ejercicio abierto para la fecha '.$this->today());
      }
   }
   
   private function crea_subcuentas($ejercicio, $cuenta, $codigo_cuenta, $descripcion)
   {
		  
		   $this->new_message('Ejercicio: ' .$ejercicio . ' Cuenta: ' .$cuenta .' Cod. Cuenta: ' .$codigo_cuenta .' Cod. Subcuenta: ' .$codigo_subcuenta . ' descripcion: ' .$descripcion);    
         
       $subc0 = $this->subcuenta->get_by_codigo((string) $codigo_cuenta, $ejercicio);
       if(!$subc0)
       {
          $datoscuenta = $this->cuenta->get_by_codigo($cuenta, $ejercicio);
      
          $subc0 = new subcuenta();
        
          $subc0->codcuenta = $cuenta;
		      $subc0->codejercicio = $ejercicio;
		      $subc0->codsubcuenta = $codigo_cuenta;
		      $subc0->descripcion = $descripcion;
		      $subc0->idcuenta = $datoscuenta->idcuenta;
		    
		      if(!$subc0->save() )
          {
              $this->new_error_msg('Error al crear la subcuenta ' .$codigo_cuenta);
          }
          else
              $this->new_message('Creada subcuenta ' .$codigo_cuenta .' - ' .$descripcion .' correctamente');    
       }
   }
}
