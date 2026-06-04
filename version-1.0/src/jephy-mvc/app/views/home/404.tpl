{extends file="layout.tpl"}

{block name="styles"}
<style>
#home-slide {
    background-color: var(--color-primary);
    display: block;
    width: 100%;
    min-height: 350px;
    background-image: url(/assets/images/banners/slide.jpg);
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    position: relative;
    top: 0;
    left: 0;
}
</style>
{/block}
{block name="home"}
<section id="home-slide">
	<div class="container">
		<div class="row justify-content-center">
			<div class="col-md-9">
				<div style="display: flex; align-items: center; flex-direction: column; width: 100%; height: 350px; ">
					<div style="border: 0px solid #f00; margin: auto 0px;">					
						
						<div>
						
							<div class="home-slide-badges">
								<span class="home-slide-top-badge">
									<span class="badge-bull"></span>
									Page Not Found
								</span>
							</div>
							
							<h1 class="home-slide-main-heading">
								404 Error: Page Not Found!
							</h1>
							
							<h3 class="home-slide-sub-heading">
								The page you seek has been temporaily moved.
							</h3>
							
						</div>
						
						<div class="home-slide-ctas">
							<a href="/">Go back to home page</a>
						</div>
						
					</div>
				</div>
				
			</div>
			
		</div>
	</div>
</section>
{/block}