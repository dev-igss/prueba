@extends('admin.master')
@section('title','Ambientes')

@section('breadcrumb')
    <li class="breadcrumb-item">
        <a href="{{ url('/admin/kardex/all') }}" class="nav-link"><i class="fas fa-database"></i> Ambientes</a>
    </li>
@endsection

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-4">
                
                <div class="panel shadow">
                    <div class="header">
                        <h2 class="title"><i class="fas fa-plus-circle"></i> Editar Servicio General</h2>
                    </div>

                    <div class="inside">
                        {!! Form::open(['url' => '/admin/servicios_general/'.$servicesg->id.'/editar', 'files' => true]) !!}
                            <label for="name"> <strong><sup style="color: red;">(*)</sup> Nombre: </strong></label>
                            <div class="input-group">
                                <span class="input-group-text" id="basic-addon1"><i class="fas fa-keyboard"></i></span>
                                {!! Form::text('name', $servicesg->name, ['class'=>'form-control']) !!}
                            </div>

                            {!! Form::submit('Guardar', ['class'=>'btn btn-success mtop16']) !!}
                        {!! Form::close() !!}
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection
