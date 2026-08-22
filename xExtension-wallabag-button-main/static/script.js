if (document.readyState && document.readyState !== 'loading')
{
  documentReady();
} else
{
  document.addEventListener('DOMContentLoaded', async () => await documentReady(), false);
}

async function documentReady()
{
  // Use capture-phase event delegation so dynamically added buttons work
  document.addEventListener('click', function (e)
  {
    const wallabagButton = e.target.closest('#stream .flux a.wallabagButton');
    if (!wallabagButton)
    {
      return;
    }

    e.preventDefault();
    e.stopPropagation();

    const active = wallabagButton.closest(".flux");
    if (!active)
    {
      return;
    }

    add_to_wallabag(wallabagButton, active);
  }, true);

  if (wallabag_button_vars.keyboard_shortcut)
  {
    document.addEventListener('keydown', function (e)
    {
      if (e.ctrlKey || e.metaKey || e.altKey || e.shiftKey || e.target.closest('input, textarea'))
      {
        return;
      }

      if (e.key === wallabag_button_vars.keyboard_shortcut)
      {
        var active = document.querySelector("#stream .flux.active");
        if (!active)
        {
          return;
        }

        var wallabagButton = active.querySelector("a.wallabagButton");
        if (!wallabagButton)
        {
          return;
        }

        add_to_wallabag(wallabagButton, active);
      }
    });
  }
}

function requestFailed(activeId, wallabagButtonImg, loadingAnimation)
{
  delete pending_entries[activeId];

  wallabagButtonImg.classList.remove("wb_disabled");
  loadingAnimation.classList.add("wb_disabled");

  badAjax(this.status == 403);
}

async function add_to_wallabag(wallabagButton, active)
{
  const url = wallabagButton.getAttribute("href");
  if (!url)
  {
    return;
  }

  let wallabagButtonImg = wallabagButton.querySelector("img");
  wallabagButtonImg.classList.add("wb_disabled");

  let loadingAnimation = wallabagButton.querySelector(".wb_lds-dual-ring");
  loadingAnimation.classList.remove("wb_disabled");

  let activeId = active.getAttribute('id');
  if (pending_entries[activeId])
  {
    return;
  }

  pending_entries[activeId] = true;
  await fetch(url,
    {
      method: "POST",
      headers:
      {
        "Content-Type": "application/json",
        "Accept": "application/json",
      },
      body: JSON.stringify({
        _csrf: context.csrf,
      })
    })
    .then(async response =>
    {
      delete pending_entries[activeId];

      wallabagButtonImg.classList.remove("wb_disabled");
      loadingAnimation.classList.add("wb_disabled");

      if (!response.ok)
      {
        if (response.status === 404)
        {
          openNotification(wallabag_button_vars.i18n.article_not_found, 'wallabag_button_bad');
        }
        requestFailed(activeId, wallabagButtonImg, loadingAnimation);
        return;
      }

      let json = await response.json();
      if (!json || json.status >= 400 || !json.response || !json.response.title)
      {
        const errorCode = (json && (json.errorCode || json.status)) || 'unknown';
        openNotification(wallabag_button_vars.i18n.failed_to_add_article_to_wallabag.replace('%s', errorCode), 'wallabag_button_bad');
        requestFailed(activeId, wallabagButtonImg, loadingAnimation);
        return;
      }

      wallabagButtonImg.setAttribute("src", wallabag_button_vars.icons.added_to_wallabag);
      openNotification(wallabag_button_vars.i18n.added_article_to_wallabag.replace('%s', json.response.title), 'wallabag_button_good');
    })
    .catch((e) =>
    {
      requestFailed(activeId, wallabagButtonImg, loadingAnimation);
    });
}
