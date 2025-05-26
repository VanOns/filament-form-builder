<form method="POST" action="{{ route('filament-form-builder.form.store', ['formId' => $form->id]) }}">
    @csrf
    @if (session('submit_notification_type') === 'content' && $success = session('submit_notification_content'))
        <div class="py-4">
            <p>{!! $success !!}</p>
        </div>
    @endif
    <div class="flex flex-col gap-4 p-6 rounded-md bg-gray-100">
        <h2>Contact form</h2>
        <div class="flex flex-col space-y-2">
            <label class="font-bold" for="name">Name *</label>
            <input
                type="text"
                id="name"
                name="name"
                placeholder="Name"
                class="bg-white border border-gray-200 rounded-md p-2 w-1/3"
                required
            />
        </div>
        <div class="flex flex-col space-y-2">
            <label class="font-bold" for="company_name">Company name *</label>
            <input
                type="text"
                id="company_name"
                name="company_name"
                placeholder="Company name"
                class="bg-white border border-gray-200 rounded-md p-2 w-1/3"
                required
            />
        </div>
        <div class="flex flex-col space-y-2">
            <label class="font-bold" for="submitter_email">Email *</label>
            <input
                type="email"
                id="submitter_email"
                name="submitter_email"
                placeholder="Email"
                class="bg-white border border-gray-200 rounded-md p-2 w-1/3"
                required
            />
        </div>
        <div class="flex flex-col space-y-2">
            <label class="font-bold" for="phone_number">Phone number *</label>
            <input
                type="text"
                id="phone_number"
                name="phone_number"
                placeholder="Phone number"
                class="bg-white border border-gray-200 rounded-md p-2 w-1/3"
                required
            />
        </div>
        <div class="flex flex-col space-y-2">
        <label class="font-bold" for="message">Your message *</label>
            <textarea
                id="message"
                name="message"
                placeholder="Your message"
                class="bg-white border border-gray-200 rounded-md p-2 w-1/2"
                required
            ></textarea>
        </div>
        <button class="w-fit p-2 text-center bg-red-500 text-white" type="submit">
            Send
        </button>
    </div>
</form>