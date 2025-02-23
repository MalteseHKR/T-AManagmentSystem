use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserInformationTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_information', function (Blueprint $table) {
            $table->id();
            $table->string('user_name');
            $table->string('user_surname');
            $table->string('user_title')->nullable();
            $table->string('user_phone')->nullable();
            $table->string('user_email')->unique();
            $table->date('user_dob')->nullable();
            $table->date('user_job_start')->nullable();
            $table->date('user_job_end')->nullable();
            $table->boolean('user_active')->default(false);
            $table->unsignedBigInteger('user_department')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_information');
    }
}