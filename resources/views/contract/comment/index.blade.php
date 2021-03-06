@extends('layout.app')

@section('content')
    <div class="panel panel-default">
        <div class="panel-heading">@lang("All Comments") - {{$contract->metadata->contract_name}} <a class="btn btn-default pull-right" href="{{route('contract.show', $contract->id)}}">@lang("Back")</a></div>
        <div class="panel-body">
            <div class="" id="myTabs">
                <ul class="nav nav-tabs">
                    <li class="active"><a id="tabAll">All</a></li>
                    <li><a href="#" id="tabMetadata">@lang('Metadata')</a></li>
                    <li><a href="#" id="tabText">@lang('Text')</a></li>
                    <li><a href="#" id="tabAnnotation">@lang('Annotation')</a></li>
                </ul>
            </div>

            <div class="tab-content">
            @forelse($comments as $comment)
                <div class="comment-section tab-pane-{{$comment->type}} active" id="{{$comment->type}}">
                    <div class="comment">
                       {{$comment->message}}
                            <div class="label label-default label-comment">{{ucfirst($comment->type)}}</div>
                       </div>
                        <div class="comment-info">
                            <span class="{{$comment->action}}">{{ucfirst($comment->action)}}</span>
                            @lang('by') <strong>{{$comment->user->name}}</strong>
                            @lang('on') {{$comment->created_at->format('D F d, Y h:i a')}}
                        </div>
                    </div>
                @empty
                    <div class="no-comment">
                        {{trans('There is no comment.')}}
                    </div>
            @endforelse
            </div>
            </div>
            {!!$comments->render()!!}
    </div>
@stop
