<template>
    <div class="min-h-screen flex items-center justify-center bg-dark-900">
      <div class="text-center">
        <div v-if="success" class="text-green-400 text-xl mb-4">
          <svg class="w-16 h-16 mx-auto mb-4" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
          </svg>
          {{ message }}
        </div>
        <div v-else class="text-gray-400 text-xl mb-4">
          {{ message }}
        </div>
        <p class="text-gray-500 text-sm">This window will close automatically in {{ countdown }} seconds...</p>
      </div>
    </div>
  </template>
  
  <script>
  export default {
    props: {
      success: Boolean,
      message: String,
    },
    data() {
      return {
        countdown: 3,
      }
    },
    mounted() {
      console.log('DocuSign callback mounted')
      console.log('Success:', this.success)
      console.log('Message:', this.message)
      console.log('Window opener exists:', !!window.opener)
      console.log('Window opener closed:', window.opener ? window.opener.closed : 'N/A')
      
      // Notify the parent window immediately
      this.notifyParent()
      
      // Start countdown
      const countdownInterval = setInterval(() => {
        this.countdown--
        if (this.countdown <= 0) {
          clearInterval(countdownInterval)
          this.closeWindow()
        }
      }, 1000)
    },
    methods: {
      notifyParent() {
        if (window.opener && !window.opener.closed) {
          console.log('Sending message to parent window...')
          
          const message = {
            type: 'docusign_complete',
            success: this.success,
            message: this.message,
          }
          
          console.log('Message payload:', message)
          console.log('Target origin:', window.location.origin)
          
          // Send message to parent
          window.opener.postMessage(message, window.location.origin)
          
          console.log('Message sent successfully')
        } else {
          console.error('Cannot notify parent - window.opener is not available or closed')
        }
      },
      closeWindow() {
        console.log('Attempting to close window...')
        window.close()
        
        // If window.close() doesn't work (some browsers prevent it), show a message
        setTimeout(() => {
          console.log('Window still open, showing manual close message')
          alert('Please close this window to return to the application.')
        }, 500)
      },
    },
  }
  </script>