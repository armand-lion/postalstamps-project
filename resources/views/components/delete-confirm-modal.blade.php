<dialog id="delete-confirm-modal" class="w-full max-w-md rounded-xl p-0 shadow-xl backdrop:bg-black/50">
    <div class="p-6">
        <h2 class="text-lg font-semibold">
            {{ __('Verwijderen bevestigen') }}
        </h2>

        <p class="mt-3 text-sm text-gray-600">
            {{ __('Weet je zeker dat je dit item wilt verwijderen?') }}
        </p>

        <div class="mt-6 flex justify-end gap-3">
            <button type="button" onclick="closeDeleteModal()"
                class="rounded-lg border border-gray-300 px-4 py-2 text-sm hover:bg-gray-100">
                {{ __('Annuleren') }}
            </button>

            <button type="button" onclick="confirmDelete()"
                class="rounded-lg bg-red-600 px-4 py-2 text-sm text-white hover:bg-red-700">
                {{ __('Verwijderen') }}
            </button>
        </div>
    </div>
</dialog>

<script>
    let deleteFormToSubmit = null;

    function openDeleteModal(form) {
        deleteFormToSubmit = form;

        document
            .getElementById('delete-confirm-modal')
            .showModal();
    }

    function closeDeleteModal() {
        document
            .getElementById('delete-confirm-modal')
            .close();

        deleteFormToSubmit = null;
    }

    function confirmDelete() {
        if (deleteFormToSubmit) {
            deleteFormToSubmit.submit();
        }
    }
</script>
