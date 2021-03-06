@extends('layout.app')

<?php
$contract_processing_completed = \App\Nrgi\Entities\Contract\Contract::PROCESSING_COMPLETE;
$contract_processing_failed = \App\Nrgi\Entities\Contract\Contract::PROCESSING_FAILED;
$contract_processing_running = \App\Nrgi\Entities\Contract\Contract::PROCESSING_RUNNING;
$contract_processing_pipline = \App\Nrgi\Entities\Contract\Contract::PROCESSING_PIPELINE;
?>

@section('script')
    <script>
        $(function () {
            var form = $('.output-type-form');

            $(form).on('submit', function (e) {
                e.preventDefault();
                var type = form.find('input[type=radio]:checked').val();
                if (typeof type != 'undefined') {
                    $.ajax({
                        url: form.attr('action'),
                        data: form.serialize(),
                        type: 'POST',
                        dataType: 'json'
                    }).done(function (response) {
                        window.location.reload()
                    })
                }
                else {
                    alert('Please select text type');
                }
            });


            var suggestion_form = $('.suggestion-form');
            $(suggestion_form).on('submit', function (e) {
                var text = $(this).find('#message').val();
                var status = $(this).find('#status').val();
                if (text == '' && status == 'rejected') {
                    e.preventDefault();
                    alert('Suggestion message is required.');
                    return false;
                }
                else{
                    $(this).find('input[type=submit]').text('loading...');
                    $(this).find('input[type=submit]').attr('disabled', 'disabled');
                    return true;
                }
            });
        })
    </script>
@stop

@section('content')
    <div class="panel panel-default">
        <div class="panel-heading">{{$contract->title}}</div>
        <div class="action-btn pull-right" style="padding: 20px;">
            <a href="{{route('activitylog.index')}}?contract={{$contract->id}}"
               class="btn btn-default">@lang('activitylog.activitylog')</a>
            <a href="{{route('contract.edit', $contract->id)}}" class="btn btn-default">@lang('contract.edit')</a>

            <div class="btn-group">
                <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    @lang('Download') <span class="caret"></span>
                </button>
                <ul class="dropdown-menu">
                    <li><a target="_blank" href="{{$contract->file_url}}">@lang('PDF')</a></li>
                    @if($contract->word_file !='')
                        <li><a href="{{route('contract.download', $contract->id)}}">@lang('Word')</a></li>
                    @endif
                </ul>
            </div>

            @if($current_user->can('delete-contract'))
                {!!Form::open(['route'=>['contract.destroy', $contract->id], 'style'=>"display:inline",
                'method'=>'delete'])!!}
                {!!Form::button(trans('contract.delete'), ['type'=>'submit','class'=>'btn btn-danger confirm',
                'data-confirm'=>trans('contract.confirm_delete')])!!}
                {!!Form::close()!!}
            @endif
        </div>

        @if($status == $contract_processing_completed)
            <div style="padding: 40px;">
                <a href="{{route('contract.review', ['id'=>$contract->id])}}"
                   class="btn btn-default">@lang('contract.view_pages')</a>
                <a href="{{route('contract.annotate', ['id'=>$contract->id])}}"
                   class="btn btn-default">@lang('contract.annotate_contract')</a>
                <br>
                <br>
                @if($contract->pdf_structure != null)
                    <p>
                        <strong>@lang('contract.pdf_type')</strong> {{ucfirst($contract->pdf_structure)}}
                    </p>
                @endif
                <p>Text type :
                    <a href="#" data-key="{{$contract->textType}}" class="text-type-block"
                       data-toggle="modal"
                       data-target=".text-type-modal">
                        @if($contract->textType =='')
                            @lang('contract.choose')

                        @else
                            <?php $label = $contract->getTextType();?>
                            <span class="label label-{{$label->color}}"> {{$label->name}}</span>
                        @endif
                    </a>

                    @if($contract->textType == 3 && is_null($contract->mturk_status))
                        {!! Form::open(['route' => ['mturk.add', $contract->id], 'method' => 'post']) !!}
                            {!! Form::button(trans('Send to Manual Transcription tasks'), ['type' =>'submit', 'class' => 'btn btn-default confirm', 'data-confirm'=>'Are you sure you want to send this contract to Mechanical Turk?']) !!}
                        {!! Form::close() !!}
                    @endif

                    @if($contract->mturk_status  == \App\Nrgi\Entities\Contract\Contract::MTURK_SENT)
                         @lang('Sent to MTurk') <a class="btn btn-default" href="{{route('mturk.tasks', $contract->id)}}">@lang('View')</a>
                    @endif

                    @if($contract->mturk_status  == \App\Nrgi\Entities\Contract\Contract::MTURK_COMPLETE)
                        @lang('MTurk task Completed')
                    @endif
                </p>

                <div class="modal fade text-type-modal" id="text-type-modal" tabindex="-1" role="dialog"
                     aria-labelledby="text-type-modal"
                     aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            {!! Form::open(['route' => ['contract.output.save', $contract->id],
                            'class'=>'output-type-form']) !!}
                            <div class="modal-header">
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                                            aria-hidden="true">&times;</span></button>
                                <h4 class="modal-title" id="myModalLabel">Choose type of output Text</h4>
                            </div>
                            <div class="modal-body">
                                <ul class="types">
                                    <li><label class="label label-success"> {!!Form::radio('text_type', 1,
                                            ($contract->textType == 1) ) !!}
                                            @lang('contract.acceptable')</label>
                                    <li><label class="label label-warning">{!!Form::radio('text_type', 2,
                                            ($contract->textType == 2)) !!} @lang('contract.needs_editing')
                                        </label>
                                    <li><label class="label label-danger">{!!Form::radio('text_type', 3,
                                            ($contract->textType == 3))
                                            !!} @lang('contract.needs_full_transcription')</label>
                                </ul>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-default"
                                        data-dismiss="modal">@lang('contract.close')</button>
                                <button type="submit" class="btn btn-primary">@lang('contract.save_changes')s</button>
                            </div>
                            {!! Form::close() !!}
                        </div>
                    </div>
                </div>
            </div>
        @elseif($status == $contract_processing_failed)
            <div class="status">@lang('contract.status') : @lang('Failed')</div>
        @elseif($status== $contract_processing_running)
            <div class="status">@lang('contract.status') : @lang('Processing')</div>
        @else($status== $contract_processing_pipline)
            <div class="status">@lang('contract.status') : @lang('Pipeline')</div>
        @endif


        @include('contract.state')
        <a style="margin-left: 15px;margin-bottom: 25px" class="btn btn-default"
           href="{{route('contract.comment.list',$contract->id)}}">View all comments</a>

        <ul class="contract-info">
            <li><strong>@lang('contract.created_by'):</strong>
                {{$contract->created_user->name}} on {{$contract->created_datetime->format('D M d, Y h:i A')}} (GMT)
            </li>

            @if(!is_null($contract->updated_user))
                <li><strong>@lang('contract.last_modified_by'):</strong> {{$contract->updated_user->name}}
                    on {{$contract->last_updated_datetime->format('D M d, Y h:i A')}} (GMT)
                </li>
            @endif

            @if(isset($contract->metadata->contract_name))
                <li>
                    <strong>@lang('contract.contract_name'):</strong> {{$contract->metadata->contract_name}}
                </li>
            @endif

            @if(isset($contract->metadata->contract_identifier))
                <li>
                    <strong>@lang('contract.contract_identifier'):</strong> {{$contract->metadata->contract_identifier}}
                </li>
            @endif


            @if(isset($contract->metadata->language))
                <?php $lang = config('metadata.language');?>
                <li>
                    <strong>@lang('contract.language'):</strong> {{$lang[$contract->metadata->language]}}
                    [{{$contract->metadata->language}}]
                </li>
            @endif
            @if(isset($contract->metadata->country->name))
                <li>
                    <strong>@lang('contract.country'):</strong> {{$contract->metadata->country->name or ''}}
                    [{{$contract->metadata->country->code or ''}}]
                    @if(isset(config('amla')[$contract->metadata->country->code]))
                        <a href="{{config('amla')[$contract->metadata->country->code]}}">@lang('contract.amla')</a>
                    @endif
                </li>
            @endif

            @if(is_array($contract->metadata->resource) && count($contract->metadata->resource)>0)
                <li><strong>@lang('contract.resource'): </strong>{{join(', ', $contract->metadata->resource)}}</li>
            @endif

            @if(isset($contract->metadata->government_entity))
                <li><strong>@lang('contract.government_entity'):</strong> {{$contract->metadata->government_entity}}
                </li>
            @endif

            @if(isset($contract->metadata->government_identifier))
                <li><strong>@lang('contract.government_identifier')
                        :</strong> {{$contract->metadata->government_identifier}}</li>
            @endif

            @if(isset($contract->metadata->type_of_contract))
                <li><strong>@lang('contract.type_of_contract'):</strong> {{$contract->metadata->type_of_contract}}</li>
            @endif
            @if(isset($contract->metadata->signature_date))
                <li><strong>@lang('contract.signature_date'):</strong> {{$contract->metadata->signature_date}}</li>
            @endif

            @if(isset($contract->metadata->document_type))
                <li><strong>@lang('contract.document_type'):</strong> {{$contract->metadata->document_type}}</li>
            @endif



            @if(isset($contract->metadata->company))
                <?php $companies = $contract->metadata->company;?>
                @if(count($companies)>0)
                    <li><h3>@lang('contract.company')</h3>
                        @foreach($companies as $k => $v)
                            <div style="margin-bottom: 20px; border-bottom:1px solid #ccc; padding-bottom:20px; ">

                                <p><strong>@lang('contract.company_name'):</strong>  {{$v->name}}</p>
                                @if(isset($v->participation_share))
                                    <p><strong>@lang('contract.participation_share'):</strong> {{$v->participation_share}}
                                    </p>
                                @endif

                                <p><strong>@lang('contract.jurisdiction_of_incorporation')
                                        :</strong> {{@trans('codelist/country')[$v->jurisdiction_of_incorporation]}}
                                </p>

                                <p><strong>@lang('contract.registry_agency'):</strong> {{$v->registration_agency}}</p>

                                <p><strong>@lang('contract.incorporation_date') :</strong> {{$v->company_founding_date}}
                                </p>

                                <p><strong>@lang('contract.company_address') :</strong> {{$v->company_address}}</p>

                                <p><strong>@lang('contract.company_number'):</strong> @if(isset($v->company_number)){{$v->company_number}}@endif</p>

                                <p><strong>@lang('contract.parent_company'):</strong> {{$v->parent_company}}</p>

                                <p><strong>@lang('contract.open_corporate')
                                        :</strong> @if(!empty($v->open_corporate_id))
                                        <a target="_blank"
                                           href="{{$v->open_corporate_id}}">{{$v->open_corporate_id}}</a>@endif
                                </p>
                                @if(isset($v->operator)) <p><strong>@lang('contract.operator'):</strong>@if($v->operator==1)Yes  @endif</p>@endif
                            </div>
                        @endforeach
                    </li>
                @endif
            @endif

            <li><h3>@lang('contract.license_and_project')</h3></li>
            @if(isset($contract->metadata->concession))
               <div class="license-wrap">
                   @foreach($contract->metadata->concession as $concession)
                       @if(isset($concession->license_name))
                           <li><strong>@lang('contract.license_name_only'):</strong> {{$concession->license_name}}</li>
                       @endif
                       @if(isset($concession->license_identifier))
                           <li><strong>@lang('contract.license_identifier_only')
                                   :</strong> {{$concession->license_identifier}}</li>
                       @endif

                   @endforeach
               </div>
            @endif
            @if(isset($contract->metadata->license_source_url))
                <li><strong>@lang('contract.license_source_url'):</strong> {{$concession->license_source_url}}
                </li>
            @endif
            @if(isset($contract->metadata->license_type))
                <li><strong>@lang('contract.license_type'):</strong> {{$contract->metadata->license_type}}</li>
            @endif
            @if(isset($contract->metadata->project_title))
                <li><strong>@lang('contract.project_title'):</strong> {{$contract->metadata->project_title}}</li>
            @endif
            @if(isset($contract->metadata->project_identifier))
                <li><strong>@lang('contract.project_identifier'):</strong> {{$contract->metadata->project_identifier}}
                </li>
            @endif
            @if(isset($contract->metadata->date_granted))
                <li><strong>@lang('contract.date_granted'):</strong> {{$contract->metadata->date_granted}}</li>
            @endif
            @if(isset($contract->metadata->year_granted))
                <li><strong>@lang('contract.year_granted'):</strong> {{$contract->metadata->year_granted}}</li>
            @endif
            @if(isset($contract->metadata->ratification_date))
                <li><strong>@lang('contract.date_of_ratification'):</strong> {{$contract->metadata->ratification_date}}
                </li>
            @endif
            @if(isset($contract->metadata->ratification_year))
                <li><strong>@lang('contract.year_of_ratification'):</strong> {{$contract->metadata->ratification_year}}
                </li>
            @endif

            <li><h3>@lang('contract.source')</h3></li>
            @if(isset($contract->metadata->source_url))
                <li><strong>Source URL:</strong> {{$contract->metadata->source_url}}</li>
            @endif
            @if(isset($contract->metadata->disclosure_mode))
                <li><strong>@lang('contract.disclosure_mode'):</strong> {{$contract->metadata->disclosure_mode}}</li>
            @endif
            @if(isset($contract->metadata->date_retrieval))
                <li><strong>@lang('contract.date_of_retrieval'):</strong> {{$contract->metadata->date_retrieval}}</li>
            @endif
            @if(isset($contract->metadata->location))
                <li><strong>@lang('contract.location'):</strong> {{$contract->metadata->location}}</li>
            @endif

                <li><h3>@lang('contract.associated_contracts')</h3></li>
                @if(!empty($translatedFrom))
                    <li><strong>@lang('contract.parent_document'):</strong>
                        <a href="{{route('contract.show',$translatedFrom[0]['id'])}}">{{json_decode($translatedFrom[0]['contract_name'])}}</a>
                    </li>
                @endif
            @if(!empty($supportingDocument))
                <div class="document-link-wrapper">
                    <li><strong>@lang('contract.supporting_documents'):</strong>
                    @foreach($supportingDocument as $contract_sup)
                        <div class="document-link">
                            <a href="{{route('contract.show',$contract_sup['id'])}}">{{json_decode($contract_sup['contract_name'])}}</a>
                        </div>
                    @endforeach
                </div>
            @endif
            <?php $catConfig = config('metadata.category');?>

            @if(isset($contract->metadata->category) && is_array($contract->metadata->category) && count($contract->metadata->category)>0)
                <li><strong>@lang('contract.category'):</strong>
                    <?php $cat = [];
                    foreach ($contract->metadata->category as $key):
                        $cat[] = $catConfig[$key];
                    endforeach;
                    ?>
                    {{join(', ', $cat)}}
                </li>
            @endif
        </ul>
        @if($status == $contract_processing_completed)
            <div class="annotation-wrap">
                <h3>@lang('contract.annotations')</h3>

                <div class="annotation-list">
                    <ul>
                        @forelse($annotations as $annotation)
                            <li>
                                {{$annotation->annotation->category}}
                                <br>
                                <span><a href="{{route('contract.pages', ['id'=>$contract->id])}}?action=annotate&page={{$annotation->document_page_no}}">{{$annotation->annotation->quote or 'pdf annotation'}} </a>[Page {{$annotation->document_page_no}}
                                    ]</span>

                                <p>{{$annotation->annotation->text}}</p>
                                @foreach($annotation->annotation->tags as $tag)
                                    <div>{{$tag}}</div>
                                @endforeach
                            </li>
                        @empty
                            <li>@lang('Annotation not created. Please create') <a style="font-size: 14px"
                                                                                  href="{{route('contract.pages', ['id'=>$contract->id])}}?action=annotate">here</a>
                            </li>
                        @endforelse
                    </ul>
                </div>
            </div>
        @endif
    </div>
@stop
