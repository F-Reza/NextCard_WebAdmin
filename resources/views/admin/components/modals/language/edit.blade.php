@if (admin_permission_by_name("admin.languages.update"))
    <div id="language-edit" class="mfp-hide large">
        <div class="modal-data">
            <div class="modal-header px-0">
                <h5 class="modal-title">{{ __("Edit Language") }}</h5>
            </div>
            <div class="modal-form-data">
                <form class="modal-form" method="POST" action="{{ setRoute('admin.languages.update') }}" enctype="multipart/form-data">
                    @csrf
                    @method("PUT")
                    <input type="hidden" name="target">
                    <div class="row mb-10-none mt-2">
                        <div class="col-xl-6 col-lg-6 form-group">
                            @include('admin.components.form.input',[
                                'label'         => __('Language Name').'*',
                                'name'          => 'edit_name',
                                'value'         => old('edit_name')
                            ])
                        </div>
                        <div class="col-xl-6 col-lg-6 form-group">
                            @include('admin.components.form.input',[
                                'label'         => __('Language Code').'*',
                                'name'          => 'edit_code',
                                'value'         => old('edit_code')
                            ])
                        </div>
                        <div class="col-xl-12 col-lg-12 form-group">
                            @include('admin.components.form.switcher',[
                                'label'         => __('Direction').'*',
                                'name'          => 'edit_dir',
                                'value'         => old('edit_dir'),
                               'options'       => [__('LTR') => 'ltr',__('RTL') => 'rtl'],
                            ])
                        </div>
                        <div class="col-xl-12 col-lg-12 form-group d-flex align-items-center justify-content-between mt-4">
                            <button type="button" class="btn btn--danger modal-close">{{ __("Cancel") }}</button>
                            <button type="submit" class="btn btn--base">{{ __("Update") }}</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push("script")
        <script>
            openModalWhenError("language-edit","#language-edit");
            $(".edit-modal-button").click(function(){
                var oldData = JSON.parse($(this).parents("tr").attr("data-item"));
                var editModal = $("#language-edit");
                console.log(oldData.dir);

                editModal.find("form").first().find("input[name=target]").val(oldData.id);
                editModal.find("input[name=edit_name]").val(oldData.name);
                editModal.find("input[name=edit_code]").val(oldData.code);
                editModal.find("input[name=edit_dir]").val(oldData.dir);

                refreshSwitchers("#language-edit");
                openModalBySelector("#language-edit");
            });
        </script>
    @endpush
@endif
