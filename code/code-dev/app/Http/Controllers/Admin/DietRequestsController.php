<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Http\Models\Diet, App\Http\Models\Bitacora, App\Http\Models\Journey, App\Http\Models\Service,  App\Http\Models\DietRequest, App\Http\Models\DietRequestDetail, App\Http\Models\DietRequestOut;
use Carbon\Carbon, Auth, Validator, Str, Config, Session, DB, Response, File, Image, PDF;
//use Elibyy\TCPDF\Facades\TCPDF;


class DietRequestsController extends Controller
{
    public function __Construct(){
        $this->middleware('auth');
        $this->middleware('IsAdmin');
        $this->middleware('UserStatus');
        $this->middleware('Permissions');
    }

    public function getHome($status){

        switch ($status) {
            case '0':
                if(Auth::user()->role == 5){
                    $diet_requests = DietRequest::where('status', '0')->where('idapplicant', Auth::user()->id)->orderBy('id', 'Asc')->get();
                }else{
                    $diet_requests = DietRequest::where('status', '0')->orderBy('id', 'Asc')->get();
                }
            break;

            case '1':
                if(Auth::user()->role == 5){
                    $diet_requests = DietRequest::where('status', '1')->where('idapplicant', Auth::user()->id)->orderBy('id', 'Asc')->get();
                }else{
                    $diet_requests = DietRequest::where('status', '1')->orderBy('id', 'Asc')->get();
                }

            break;

            case '2':
                if(Auth::user()->role == 5){
                    $diet_requests = DietRequest::where('status', '2')->where('idapplicant', Auth::user()->id)->orderBy('created_at', 'Desc')->get();
                }else{
                    $diet_requests = DietRequest::where('status', '2')->orderBy('created_at', 'Desc')->get();
                }
            break;

            case 'todas':
                if(Auth::user()->role == 5){
                    $diet_requests = DietRequest::where('idapplicant', Auth::user()->id)->orderBy('id', 'Asc')->get();
                }else{
                    $diet_requests = DietRequest::orderBy('id', 'Asc')->get();
                }
            break;

            case 'anuladas':
                if(Auth::user()->role == 5){
                    $diet_requests = DietRequest::onlyTrashed()->where('idapplicant', Auth::user()->id)->orderBy('id', 'Asc')->get();
                }else{
                    $diet_requests = DietRequest::onlyTrashed()->orderBy('id', 'Asc')->get();
                }

            break;
        }

        $data = [
            'diet_requests' => $diet_requests
        ];

    	return view('admin.diet_request.home', $data);
    }

    public function getDietRequestAdd(){

        $hora = Carbon::now()->format('H:i');
        $fecha = Carbon::now()->format('Y-m-d');

        if(Auth::user()->role == 5):
            $solicitud_fuera = DietRequestOut::whereDate('created_at',$fecha)->where('idapplicant', Auth::user()->id)->where('status',1)->first();
        endif;

        if($solicitud_fuera):
            $journeys = Journey::where('id', $solicitud_fuera->idjourney)->get();
            $diets = Diet::where('id','<>',17)->pluck('name','id');
            $services = Service::where('type','1')
                ->where('parent_id', '<>', '2')
                ->where('unit_id', '1')
                ->get();
            $user = Auth::user()->name.' '.Auth::user()->lastname;
            $solicitud_fuera_tiempo = 1;
            $solicitud_fuera_id = $solicitud_fuera->id;
            $cantidad_dietas = $solicitud_fuera->amount_diets;

            $data = [
                'journeys' => $journeys,
                'diets' => $diets,
                'services' => $services,
                'user' => $user,
                'cantidad_dietas' => $cantidad_dietas,
                'solicitud_fuera_tiempo' => $solicitud_fuera_tiempo,
                'solicitud_fuera_id' => $solicitud_fuera_id

            ];

            return view('admin.diet_request.add_out_time', $data);
        else:
            $journeys = Journey::get();
            $diets = Diet::where('id','<>',17)->pluck('name','id');
            $services = Service::where('type','1')
                ->where('parent_id', '<>', '2')
                ->where('unit_id', '1')
                ->get();
            $user = Auth::user()->name.' '.Auth::user()->lastname;

            $data = [
                'journeys' => $journeys,
                'diets' => $diets,
                'services' => $services,
                'user' => $user
            ];

            return view('admin.diet_request.add', $data);
        endif;

        


    }

    public function postDietRequestAdd(Request $request){
        if($request->input('solicitud_fuera_tiempo') != NULL && $request->input('solicitud_fuera_tiempo') == 1):

            $rules = [

                'idjourney' => 'required'
            ];

            $messages = [

                'idjourney.required' => 'Se requiere que seleccione la jornada de la solicitud a realizar.'
            ];

            $validator = Validator::make($request->all(),$rules,$messages);

            if($validator->fails()):
                return back()->withErrors($validator)->with('messages', '¡Se ha producido un error!.')
                ->with('typealert', 'danger')->withInput();
            else:
                $solicitud_fuera = DietRequestOut::findOrFail($request->input('solicitud_fuera_id'));

                $jornada = Journey::findOrFail($request->get('idjourney'));

                switch($solicitud_fuera->time_available):
                    case 1:
                        $hora_disponible = $solicitud_fuera->created_at->addMinutes(5)->format('H:i');
                    break;

                    case 2:
                        $hora_disponible = $solicitud_fuera->created_at->addMinutes(10)->format('H:i');
                    break;

                    case 3:
                        $hora_disponible = $solicitud_fuera->created_at->addMinutes(15)->format('H:i');
                    break;

                    case 4:
                        $hora_disponible = $solicitud_fuera->created_at->addMinutes(20)->format('H:i');
                    break;

                    case 5:
                        $hora_disponible = $solicitud_fuera->created_at->addMinutes(25)->format('H:i');
                    break;

                    case 6:
                        $hora_disponible = $solicitud_fuera->created_at->addMinutes(30)->format('H:i');
                    break;

                endswitch;

                //return $hora_disponible;

                $hora_actual = Carbon::now()->format('H:i');

                if( $hora_actual > $hora_disponible ):
                    $solicitud_fuera->status = 2;
                    $solicitud_fuera->save();
                    return redirect('/admin/solicitud_dietas/1')->with('messages', '¡Excedio el tiempo habilitado para realizar la solicitud!.')
                            ->with('typealert', 'warning');

                else:
                    DB::beginTransaction();

                    $ingreso = new DietRequest;
                    $ingreso->id=$request->get('id');
                    $ingreso->idjourney =$request->get('idjourney');
                    $ingreso->idapplicant = Auth::user()->id;
                    $ingreso->idapplicant_service=$request->get('idservice');
                    $ingreso->status = 1;
                    $ingreso->save();

                    $idarticulo=$request->get('idarticulo');
                    $cantidad=$request->get('cantidad');
                    //$especificar=$request->get('especificar');
                    $idcaracteristica1=$request->get('idcaracteristica1');
                    $idcaracteristica2=$request->get('idcaracteristica2');
                    $idcaracteristica3=$request->get('idcaracteristica3');
                    $idcaracteristica4=$request->get('idcaracteristica4');
                    $idcaracteristica5=$request->get('idcaracteristica5');

                    $cont=0;

                    while ($cont<count($idarticulo)) {
                        $detalle=new DietRequestDetail();
                        $detalle->iddiet_request=$ingreso->id;
                        $detalle->iddiet=$idarticulo[$cont];
                        $detalle->bed_number=$cantidad[$cont];
                        //$detalle->specify=$especificar[$cont];
                        $detalle->type_pack=$idcaracteristica1[$cont];
                        $detalle->type_diet_1=$idcaracteristica2[$cont];
                        $detalle->type_diet_hiposodicas=$idcaracteristica3[$cont];
                        $detalle->type_diet_renal=$idcaracteristica4[$cont];
                        $detalle->type_diet_de_viaje=$idcaracteristica5[$cont];
                        $detalle->save();
                        $cont=$cont+1;

                    }

                    $ingreso->total_diets = $cont;

                    DB::commit();


                    if($ingreso->save()):
                        $b = new Bitacora;
                        $b->action = "Registro de solucitud de dietas. ";
                        $b->user_id = Auth::id();
                        $b->save();

                        $solicitud_fuera->status = 3;
                        $solicitud_fuera->save();

                        return redirect('/admin/solicitud_dietas/1')->with('messages', '¡Solicitud registrada y guardada con exito!.')
                            ->with('typealert', 'success');
                    endif;

                endif;




            endif;

        else:
            $rules = [

                'idjourney' => 'required'
            ];

            $messages = [

                'idjourney.required' => 'Se requiere que seleccione la jornada de la solicitud a realizar.'
            ];

            $validator = Validator::make($request->all(),$rules,$messages);

            if($validator->fails()):
                return back()->withErrors($validator)->with('messages', '¡Se ha producido un error!.')
                ->with('typealert', 'danger')->withInput();
            else:

                $jornada = Journey::findOrFail($request->get('idjourney'));
                $hora_actual = Carbon::now()->format('H:i');
                //return $hora_actual;

                if($hora_actual >= $jornada->start_time && $hora_actual <= $jornada->end_time):
                    DB::beginTransaction();

                    $ingreso = new DietRequest;
                    $ingreso->id=$request->get('id');
                    $ingreso->idjourney =$request->get('idjourney');
                    $ingreso->idapplicant = Auth::user()->id;
                    $ingreso->idapplicant_service=$request->get('idservice');
                    $ingreso->status = 1;
                    $ingreso->save();

                    $idarticulo=$request->get('idarticulo');
                    $cantidad=$request->get('cantidad');
                    //$especificar=$request->get('especificar');
                    $idcaracteristica1=$request->get('idcaracteristica1');
                    $idcaracteristica2=$request->get('idcaracteristica2');
                    $idcaracteristica3=$request->get('idcaracteristica3');
                    $idcaracteristica4=$request->get('idcaracteristica4');
                    $idcaracteristica5=$request->get('idcaracteristica5');

                    $cont=0;
                    $cont_npo=0;

                    while ($cont<count($idarticulo)) {
                        $detalle=new DietRequestDetail();
                        $detalle->iddiet_request=$ingreso->id;
                        $detalle->iddiet=$idarticulo[$cont];
                        if($idarticulo[$cont] == 18):
                            $cont_npo=$cont_npo+1;
                        endif;
                        $detalle->bed_number=$cantidad[$cont];
                        //$detalle->specify=$especificar[$cont];
                        $detalle->type_pack=$idcaracteristica1[$cont];
                        $detalle->type_diet_1=$idcaracteristica2[$cont];
                        $detalle->type_diet_hiposodicas=$idcaracteristica3[$cont];
                        $detalle->type_diet_renal=$idcaracteristica4[$cont];
                        $detalle->type_diet_de_viaje=$idcaracteristica5[$cont];
                        $detalle->save();
                        $cont=$cont+1;
                    }

                    $ingreso->total_diets = $cont-$cont_npo;

                    DB::commit();


                    if($ingreso->save()):
                        $b = new Bitacora;
                        $b->action = "Registro de solucitud de dietas. ";
                        $b->user_id = Auth::id();
                        $b->save();

                        return redirect('/admin/solicitud_dietas/1')->with('messages', '¡Solicitud registrada y guardada con exito!.')
                            ->with('typealert', 'success');
                    endif;
                else:
                    return back()->with('messages', '¡El tiempo de alimentación seleccionado está fuera del horario de solicitud establecido!.')
                            ->with('typealert', 'warning');

                endif;




            endif;
        endif;



    }

    public function getDietRequestServida($id, $cantidad){

        $dr = DietRequest::findOrFail($id);

        if($cantidad > $dr->total_diets){
            return back()->with('messages', '¡Esta sirviendo más dietas de las solicitadas, verifique sus datos!.')
                        ->with('typealert', 'warning');
        }else{
            $dr->diets_served = $cantidad;
            $dr->iduser_served = Auth::id();
            $dr->status = 2;

            if($dr->save()):
                $b = new Bitacora;
                $b->action = "Dieta con id: ".$dr->id." fue servida, con: ".$cantidad." dietas";
                $b->user_id = Auth::id();
                $b->save();

                return back()->with('messages', '¡Solicitud de dieta servida y guardada con exito!.')
                    ->with('typealert', 'success');
            endif;
        }



    }


    public function getDietRequestChangeDietsServida($id, $cantidad){

        $dr = DietRequest::findOrFail($id);
        $dietas_servidas_ant = $dr->diets_served;

        if($cantidad > $dr->total_diets){
            return back()->with('messages', '¡Esta sirviendo más dietas de las solicitadas, verifique sus datos!.')
                        ->with('typealert', 'warning');
        }else{
            $dr->diets_served = $cantidad;
            $dr->iduser_served = Auth::id();
            $dr->status = 2;

            if($dr->save()):
                $b = new Bitacora;
                $b->action = "Dieta con id: ".$dr->id." fue modificada la cantidad de dietas servidas, de: ".$dietas_servidas_ant." dietas a: " .$cantidad." dietas";
                $b->user_id = Auth::id();
                $b->save();

                return back()->with('messages', '¡Solicitud de dieta servida y guardada con exito!.')
                    ->with('typealert', 'success');
            endif;
        }



    }

    public function getDietRequestView($id){
        $diet_request = DietRequest::findOrFail($id);
        $iddiet_request = $diet_request->id;
        $details = DietRequestDetail::where('iddiet_request', $iddiet_request)->get();


        $data = [
            'diet_request' => $diet_request,
            'details' => $details
        ];

        return view('admin.diet_request.view', $data);
    }

    public function getDietRequestDelete($id){
        $dr = DietRequest::findOrFail($id);
        $dr1 = DietRequest::findOrFail($id);
        $dr1->status = 3;
        $dr1->update();

        if($dr->delete()):
            $b = new Bitacora;
            $b->action = "Se anulo la solicitud de dietas con id: ".$dr->id;
            $b->user_id = Auth::id();
            $b->save();

            return back()->with('messages', '¡Solicitud enviada a la papelera de reciclaje!.')
                    ->with('typealert', 'success');
        endif;
    }

    public function getDietRequestPdf($id){
        $diet_request = DietRequest::findOrFail($id);
        $iddiet_request = $diet_request->id;
        $details = DietRequestDetail::where('iddiet_request', $iddiet_request)->get();

        $subtotales = DB::table('diet_request_details')
                 ->select('iddiet', DB::raw('count(iddiet) as subtotal'))
                 ->where('iddiet_request', $iddiet_request)
                 ->groupBy('iddiet')
                 ->get();

        $subtotales_otras = DB::table('diet_request_details')
                 ->select( DB::raw('count(iddiet) as subtotal'))
                 ->where('iddiet_request', $iddiet_request)
                 ->whereIn('iddiet', ['19','20','21','22','23','24','25','26','27','28','29'])
                 ->get();

        //return $subtotales_otras;

        $data = [
            'diet_request' => $diet_request,
            'details' => $details,
            'subtotales' => $subtotales,
            'subtotales_otras' => $subtotales_otras
        ];

        $pdf = PDF::loadView('admin.diet_request.print',$data)->setPaper('a4', 'portrait');
        return $pdf->stream('ING-7.pdf');
    }

    public function getDietRequestPdfLote($jornada){
        $hoy = Carbon::now()->format('Y-m-d');
        $diet_request = DietRequest::whereDate('created_at', $hoy)->where('idjourney', $jornada)->where('status',1)->get();
        $details = DietRequestDetail::whereDate('created_at', $hoy)->get();



        $subtotales = DB::table('diet_request_details')
                 ->select('iddiet_request','iddiet', DB::raw('count(iddiet) as subtotal'))
                 ->groupBy('iddiet', 'iddiet_request')
                 ->get();

        //return $subtotales;

        $subtotales_otras = DB::table('diet_request_details')
                    ->select('iddiet_request', DB::raw('count(iddiet) as subtotal'))
                    ->whereIn('iddiet', ['19','20','21','22','23','24','25','26','27','28','29'])
                    ->groupBy('iddiet_request')
                    ->get();

        //return $subtotales_otras;

        $subtotales_diabeticas = DB::table('diet_request_details')
                    ->select('iddiet_request', DB::raw('count(iddiet) as subtotal'))
                    ->whereIn('iddiet', ['8','9','10','11'])
                    ->groupBy('iddiet_request')
                    ->get();

        $data = [
            'diet_request' => $diet_request,
            'details' => $details,
            'subtotales' => $subtotales,
            'subtotales_otras' => $subtotales_otras,
            'subtotales_diabeticas' => $subtotales_diabeticas
        ];

        $pdf = PDF::loadView('admin.diet_request.print_lote',$data)->setPaper('a4', 'portrait');
        return $pdf->stream('ING-7.pdf');
    }
}
