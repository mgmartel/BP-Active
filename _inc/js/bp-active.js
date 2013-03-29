//;(function($) {
  var BP_Active = function($) {
    /**
     * @todo Don't hang everything to the bpA object
     */
    var bpA = {};
    bpA.handler = {};

    bpA.init =  function() {
      $form = $("#whats-new-form");
      $text = $form.find('textarea[name="whats-new"]');
      $buttonLocation = $form.find('#whats-new-options');
      bpA.setup_interface().setupHandlers().submitBinder().setupTriggers();
    }

    bpA.setup_interface = function() {
      var html = '<div class="bpa_actions_container">' +
                  '<div class="bpa_controls_container"></div>' +
                  '<div class="bpa_preview_container"></div>' +
                  '<div class="bpa_action_container"></div>' +
                  '<input type="button" id="bpa_cancel_action" value="' + l10nBpa.cancel + '" style="display:none" />' +
                 '</div>';
      $form.wrap('<div class="bpa_form_container" />');
      $buttonLocation.after(html);

      return bpA;
    }

    bpA.setupHandlers = function() {
      bpA.handler.images = new bpA.imageHandler;
      bpA.handler.link = new bpA.linkHandler;

      return bpA;
    }

    bpA.imageHandler = function() {
      $container = $(".bpa_controls_container");

      var createMarkup = function () {
        var html = '<div id="bpa_tmp_photo"></div>';// +
          '<ul id="bpa_tmp_photo_list"></ul>';
        $container.append(html);

        var uploader = new qq.FileUploader({
          "element": $('#bpa_tmp_photo')[0],
          "listElement": $('#bpa_tmp_photo_list')[0],
          "allowedExtensions": ['jpg', 'jpeg', 'png', 'gif'],
          "action": ajaxurl,
          "params": {
            action: "bpa_preview_photo",
            _nonce: bpaVars.nonce,
          },
          "onSubmit": function (id) {
            if (!parseInt(l10nBpa._max_images)) return true; // Skip check
            id = parseInt(id);
            if (!id) id = $("img.bpa_preview_photo_item").length;
            if (!id) return true;
            if (id < parseInt(l10nBpa._max_images)) return true;
            if (!$("#bpa-too_many_photos").length) $(".bpa_preview_container").after(
              '<div id="message" class="error too-many-photos"><p>' + l10nBpa.images_limit_exceeded + '</p></div>'
            );
            return false;
          },
          "onComplete": createPhotoPreview,
          template: '<div class="qq-uploader">' +
                    '<div class="qq-upload-drop-area"><span>' + l10nBpa.drop_files + '</span></div>' +
                    '<div class="qq-upload-button photo-icon"></div>' +
                    '<ul class="qq-upload-list"></ul>' +
                 '</div>'
        });
      };

      var createPhotoPreview = function (id, fileName, resp) {
        if (! resp || "error" in resp) return false;
        var html = '<img class="bpa_preview_photo_item" src="' + bpaVars.tempImageUrl + resp.file + '" width="80px" />' +
          '<input type="hidden" class="bpa_photos_to_add" name="bpa_photos[]" value="' + resp.file + '" />';
        $('.bpa_preview_container').append(html);
        $('.bpa_action_container').html(
          '<input type="button" class="button" id="bpa_cancel" value="' + l10nBpa.cancel + '" /></p>'
        );
        $("#bpa_cancel_action").hide();
      };

      var removeTempImages = function (rti_callback) {
        var $imgs = $('input.bpa_photos_to_add');
        if (!$imgs.length) return rti_callback();
        $.post(ajaxurl, {"action":"bpa_remove_temp_images", "data": $imgs.serialize().replace(/%5B%5D/g, '[]'), "_nonce": bpaVars.nonce }, function (data) {
          rti_callback();
        });
      };

      var processForSave = function () {
        var $imgs = $('input.bpa_photos_to_add');
        var imgArr = [];
        $imgs.each(function () {
          imgArr[imgArr.length] = $(this).val();
        });
        return imgArr;
      };

      var init = function () {
        $container.empty();
        $('.bpa_preview_container').empty();
        $('.bpa_action_container').empty();
        createMarkup();
      };

      var destroy = function (callback) {
        removeTempImages(function() {
          $container.empty();
          $('.bpa_preview_container').empty();
          $('.bpa_action_container').empty();
          if (callback) callback();
        });
      };

      var reset = function(callback) {
        destroy(function() { createMarkup(); callback(); });
      }

      removeTempImages(init);

      return {"destroy": destroy, "get": processForSave, "reset" : reset };
    };

    bpA.linkHandler = function() {
      var curImages = new Array(),
          currData = {},
          oembedRegexp = new RegExp( '\\b' + bpaOembedHandlers.join('\\b|\\b') + '\\b'),
          $liveUrl;

      /**
       * @todo Cancel the last call? (On submit)
       */
      var reset = function(callback) {
          curImages = new Array(),
          currData = {};

          $('textarea').trigger('clear');
          $('.liveurl-loader').hide();

          var liveUrl   = $liveUrl;
              liveUrl.hide('fast');
              liveUrl.find('.video').html('').hide();
              liveUrl.find('.image').html('');
              liveUrl.find('.controls .prev').addClass('inactive');
              liveUrl.find('.controls .next').addClass('inactive');
              liveUrl.find('.thumbnail').hide();
              liveUrl.find('.image').hide();

          if (callback) callback();
      }

      var showLoader = function() {
        $('.liveurl-loader').show();
      };
      var hideLoader = function(){
        $('.liveurl-loader').hide();
      };

      var init = function() {
        createMarkup();
        $liveUrl   = $('.bpa_form_container .liveurl');
        $text.liveUrl({
          oEmbedHandler : oEmbedHandler(),

          loadStart : showLoader,
          loadEnd : hideLoader,
          success : function(data)
          {
            $.extend(currData,data);

            var output = $liveUrl;
            output.find('.title').text(data.title);
            output.find('.description').text(data.description);
            output.find('.url').text(data.url);
            output.find('.image').empty();

            output.find('.close').one('click', function()
            {
              //var liveUrl   = $(this).parent();
              var liveUrl = output;
              liveUrl.hide('fast');
              liveUrl.find('.video').html('').hide();
              liveUrl.find('.image').html('');
              liveUrl.find('.controls .prev').addClass('inactive');
              liveUrl.find('.controls .next').addClass('inactive');
              liveUrl.find('.thumbnail').hide();
              liveUrl.find('.image').hide();

              $('textarea').trigger('clear_link');
              curImages = new Array();
            });

            output.show('fast');

            if (data.video != null) {
              var ratioW    = data.video.width /350;
              data.video.width = 350;
              data.video.height = data.video.height / ratioW;

              var video =
              '<object width="' + data.video.width + '" height="' + data.video.height + '">' +
                '<param name="movie"' +
                   'value="' + data.video.file + '"></param>' +
                '<param name="allowScriptAccess" value="always"></param>' +
                '<embed src="' + data.video.file + '"' +
                   'type="application/x-shockwave-flash"' +
                   'allowscriptaccess="always"' +
                   'width="' + data.video.width + '" height="' + data.video.height + '"></embed>' +
              '</object>';
              output.find('.video').html(video).show();


            }
          },
          addImage : function(image)
          {
            var output = $liveUrl;
            var jqImage = $(image);
            jqImage.attr('alt', 'Preview');

            if ((image.width / image.height) > 7
            || (image.height / image.width) > 4 ) {
              // we dont want extra large images...
              return false;
            }

            curImages.push(jqImage.attr('src'));
            output.find('.image').append(jqImage);


            if (curImages.length == 1) {
              // first image...

              output.find('.thumbnail .current').text('1');
              output.find('.thumbnail').show();
              output.find('.image').show();
              jqImage.addClass('active');

            }

            if (curImages.length == 2) {
              output.find('.controls .next').removeClass('inactive');
            }

            output.find('.thumbnail .max').text(curImages.length);
          }
        });


        $liveUrl.on('click', '.controls .button', function()
        {
          var self    = $(this);
          var liveUrl   = $(this).parents('.liveurl');
          var content   = liveUrl.find('.image');
          var images   = $('img', content);
          var activeImage = $('img.active', content);

          if (self.hasClass('next'))
             var elem = activeImage.next("img");
          else var elem = activeImage.prev("img");

          if (elem.length > 0) {
            activeImage.removeClass('active');
            elem.addClass('active');
            liveUrl.find('.thumbnail .current').text(elem.index() +1);

            if (elem.index() +1 == images.length || elem.index()+1 == 1) {
              self.addClass('inactive');
            }
          }

          if (self.hasClass('next'))
             var other = elem.prev("img");
          else var other = elem.next("img");

          if (other.length > 0) {
            if (self.hasClass('next'))
                self.prev().removeClass('inactive');
            else  self.next().removeClass('inactive');
          } else {
            if (self.hasClass('next'))
                self.prev().addClass('inactive');
            else  self.next().addClass('inactive');
          }
        });
      };

      var oEmbedHandler = function(url) {
        var already = [],
            preview = false,
            $oembed_container = $('#bpa_oembed_preview_container'),
            $oembed_content = $oembed_container.find('.content'),
            $oembed_close = $oembed_container.find('.close');

        var hasoEmbed = function(url) {
          return oembedRegexp.test(url);
        };

        var init = function(url) {
          // First run the test
          if ( ! hasoEmbed(url) )
            return false;
          if (isDuplicate(url, already) || preview)
            return true;

          showLoader();
          $.post(ajaxurl,{
            action: 'bpa_preview_oembed_link',
            _nonce: bpaVars.nonce,
            data: url
          },function(response) {
            // URL already loaded, or preview is already shown.
            if (isDuplicate(url, already) || preview) {
                hideLoader();
                return false;
            }

            hideLoader();
            already.push(url);
            preview = true;
            $oembed_content.html(response);
            $oembed_container.show();
            currData.embed = url;

            $oembed_close.one('click',function() { embed_close(); });

          });

          $text.on('clear, clear_embed',function() { embed_reset();});
          return true;
        };

        var embed_reset = function() {
          embed_close();
          already = [];
        };

        var embed_close = function() {
          $oembed_content.empty();
          $oembed_container.hide();
          currData.embed = '';
          preview = false;
        }

        var isDuplicate = function(url, array) {
            var duplicate = false;
            $.each(array, function(key, val)
            {
                if (val == url) {
                    duplicate = true;
                }
            });

            return duplicate;
        };

        return { maybe_oembed: init }
      }

      var createMarkup = function() {
        var html =
          "<div class='liveurl-loader'></div>" +
          "<div id='bpa_oembed_preview_container'>" +
            "<div class='close' title='Close'></div>" +
            "<div class='content'></div>" +
          "</div>" +
          "<div class='liveurl'>" +
              "<div class='close' title='Close'></div>" +
             " <div class='inner'>" +
                  "<div class='image'> </div>" +
                  "<div class='details'>" +
                      "<div class='info'>" +
                          "<div class='title'> </div>" +
                          "<div class='description'> </div> " +
                          "<div class='url'> </div>" +
                      "</div>" +
                      "<div class='thumbnail'>" +
                          "<div class='pictures'>" +
                              "<div class='controls'>" +
                                  "<div class='prev button inactive'></div>" +
                                  "<div class='next button inactive'></div>" +
                                  "<div class='count'>" +
                                      "<span class='current'>0</span><span> of </span><span class='max'>0</span>" +
                                  "</div>" +
                              "</div>" +
                          "</div>" +
                      "</div>" +
                      "<div class='video'></div>" +
                  "</div>" +
              "</div>" +
          "</div>";
        $text.after(html);
      }

      var get = function() {
        var data = currData;

        var content   = $liveUrl.find('.image');
        var activeImage = $('img.active', content).attr('src');
        if ( activeImage ) data.image = activeImage;

        return data;
      };

      init();
      return { get: get, reset: reset };
    };

    bpA.getAll = function() {
      var handler = bpA.handler;
      return { images: handler.images.get(), link: handler.link.get() }
    }

    bpA.reset = function(el) {
      bpA.handler.link.reset(function() {
        bpA.handler.images.reset(function() {
          if (el)
            $(el).removeClass('loading');
        });
      });
      return bpA;
    }


    /**
     * From bp-default's global.js
     */
    bpA.submitBinder = function() {
      jq("input#aw-whats-new-submit").off('click').click( function() {

        var button = jq(this);
        var form = button.parent().parent().parent().parent();

        form.children().each( function() {
          if ( jq.nodeName(this, "textarea") || jq.nodeName(this, "input") )
            jq(this).prop( 'disabled', true );
        });

        /* Remove any errors */
        jq('div.error').remove();
        button.addClass('loading');
        button.prop('disabled', true);

        /* Default POST values */
        var object = '';
        var item_id = jq("#whats-new-post-in").val();
        var content = jq("textarea#whats-new").val();

        /* Set object for non-profile posts */
        if ( item_id > 0 ) {
          object = jq("#whats-new-post-object").val();
        }

        var data = bpA.getAll();

        jq.post( ajaxurl, {
          action: 'bpa_post_update',
          'cookie': encodeURIComponent(document.cookie),
          '_wpnonce_post_update': jq("input#_wpnonce_post_update").val(),
          'content': content,
          'object': object,
          'item_id': item_id, // == group_id
          '_bp_as_nonce': jq('#_bp_as_nonce').val() || '',
          'data': data
        },
        function(response) {

          form.children().each( function() {
            if ( jq.nodeName(this, "textarea") || jq.nodeName(this, "input") ) {
              jq(this).prop( 'disabled', false );
            }
          });

          /* Check for errors and append if found. */
          if ( response[0] + response[1] == '-1' ) {
            form.prepend( response.substr( 2, response.length ) );
            jq( 'form#' + form.attr('id') + ' div.error').hide().fadeIn( 200 );
          } else {

            bpA.reset();
            /**
             * Handle image scaling in previews.
             */
            $(".bpa_final_link img").each(function () {
              $(this).width($(this).parents('div').width());
            });

            if ( 0 == jq("ul.activity-list").length ) {
              jq("div.error").slideUp(100).remove();
              jq("div#message").slideUp(100).remove();
              jq("div.activity").append( '<ul id="activity-stream" class="activity-list item-list">' );
            }

            jq("ul#activity-stream").prepend(response);
            jq("ul#activity-stream li:first").addClass('new-update');

            if ( 0 != jq("#latest-update").length ) {
              var l = jq("ul#activity-stream li.new-update .activity-content .activity-inner p").html();
              var v = jq("ul#activity-stream li.new-update .activity-content .activity-header p a.view").attr('href');

              var ltext = jq("ul#activity-stream li.new-update .activity-content .activity-inner p").text();

              var u = '';
              if ( ltext != '' )
                u = l + ' ';

              u += '<a href="' + v + '" rel="nofollow">' + BP_DTheme.view + '</a>';

              jq("#latest-update").slideUp(300,function(){
                jq("#latest-update").html( u );
                jq("#latest-update").slideDown(300);
              });
            }

            jq("li.new-update").hide().slideDown( 300 );
            jq("li.new-update").removeClass( 'new-update' );
            jq("textarea#whats-new").val('');
          }

          jq("#whats-new-options").animate({
            height:'0px'
          });
          jq("form#whats-new-form textarea").animate({
            height:'20px'
          });
          jq("#aw-whats-new-submit").prop("disabled", true).removeClass('loading');
        });

        return false;
      });
      $('#bpa_cancel').live('click', function () { $(this).addClass('loading');
        //bpA.reset(this);
        $("div.too-many-photos").slideUp();
        bpA.handler.images.reset( function() { $(this).removeClass('loading'); });
      } );

      return bpA;
    };

    bpA.setupTriggers = function(is_setup) {
      jQuery('#whats-new').one("focus", function(){
        jQuery(".bpa_actions_container").css('height','initial') //.animate({height:'40px'},function() { jQuery(this).css("height","initial"); });
      });

      /**
       * @todo Also trigger on file drop
       */
      jQuery("div.bpa_form_container").one("click", function() {
        jQuery('#whats-new').trigger("focus");
      });

      /**
       * @todo Don't remove when ajax returns error
       */
      if (!is_setup) {
        // Is So Meta
        jQuery("input#aw-whats-new-submit").click ( function() {
          jQuery(".bpa_actions_container").css('height', '0px');
          bpA.setupTriggers(true); //.animate({height:'0px'}, function() { bpA.setupTriggers(); } );
        });
      }

      return bpA;
    };

    return bpA;

  };
  jQuery(document).ready(function() {
    bpA = BP_Active(jQuery);
    bpA.init();
  });